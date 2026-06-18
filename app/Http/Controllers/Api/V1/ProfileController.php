<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\ChangePasswordRequest;
use App\Http\Requests\Api\V1\GeneratePictureRequest;
use App\Http\Requests\Api\V1\SetPasswordRequest;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\ApiTokenService;
use App\Services\AvatarService;
use App\Services\ImageGenerationService;
use App\Services\ModerationService;
use App\Services\RatingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

class ProfileController extends ApiController
{
    private const PROFILE_IMAGE_SIZE = 300;

    private const PROFILE_IMAGE_QUALITY = 75;

    private const HEADER_IMAGE_WIDTH = 1500;

    private const HEADER_IMAGE_QUALITY = 80;

    public function __construct(
        private AvatarService $avatarService,
        private ModerationService $moderation,
        private ImageGenerationService $imageGeneration,
        private RatingService $rating,
        private ApiTokenService $tokens,
    ) {}

    /**
     * GET /users/{username} — public profile.
     */
    public function show(Request $request, string $username): JsonResponse
    {
        $user = User::where('username', $username)
            ->withCount('posts')
            ->withSum('posts', 'total_votes')
            ->firstOrFail();

        return $this->ok([
            'user' => (new UserResource($user))->resolve($request),
            'stats' => [
                'posts_count' => (int) $user->posts_count,
                'total_votes_received' => (int) ($user->posts_sum_total_votes ?? 0),
            ],
            'badges' => array_values($this->rating->getUserBadges($user)),
        ]);
    }

    /**
     * PUT /me — update the authenticated user's profile.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $lang = $user->locale ?? Config::get('app.locale');
        $errors = [];

        if ($request->filled('first_name') && ! $this->moderation->moderateText($request->first_name, $lang)['is_appropriate']) {
            $errors['first_name'][] = __('messages.content_inappropriate', ['field' => 'first_name']);
        }
        if ($request->filled('last_name') && ! $this->moderation->moderateText($request->last_name, $lang)['is_appropriate']) {
            $errors['last_name'][] = __('messages.content_inappropriate', ['field' => 'last_name']);
        }
        if ($request->filled('username') && $request->username !== $user->username
            && ! $this->moderation->moderateText($request->username, $lang)['is_appropriate']) {
            $errors['username'][] = __('messages.content_inappropriate', ['field' => 'username']);
        }
        if ($request->hasFile('profile_picture') && ! $this->moderation->moderateImage($request->file('profile_picture'), $lang)['is_appropriate']) {
            $errors['profile_picture'][] = __('messages.image_inappropriate');
        }

        // External links + moderation.
        $linksForStorage = [];
        foreach ((array) $request->input('external_links', []) as $i => $link) {
            if (empty($link)) {
                continue;
            }
            $sanitized = filter_var(trim($link), FILTER_SANITIZE_URL);
            if (! (filter_var($sanitized, FILTER_VALIDATE_URL) && Str::startsWith($sanitized, ['http://', 'https://']))) {
                $errors["external_links.$i"][] = __('messages.external_link_invalid_url_error');

                continue;
            }
            if (! $this->moderation->moderateUrl($sanitized, $lang)['is_appropriate']) {
                $errors["external_links.$i"][] = __('messages.external_link_inappropriate');

                continue;
            }
            $linksForStorage[] = $sanitized;
        }

        if (! empty($errors)) {
            return $this->error(__('messages.content_inappropriate', ['field' => 'profile']), 422, 'content_rejected', ['errors' => $errors]);
        }

        $data = [];
        foreach (['first_name', 'last_name', 'username'] as $f) {
            if ($request->has($f)) {
                $data[$f] = $request->input($f);
            }
        }
        foreach (['show_voted_posts_publicly', 'receives_notifications'] as $f) {
            if ($request->has($f)) {
                $data[$f] = $request->boolean($f);
            }
        }
        if ($request->has('ai_insight_preference')) {
            $data['ai_insight_preference'] = $request->input('ai_insight_preference');
        }
        if ($request->has('locale')) {
            $data['locale'] = $request->input('locale') ?: null;
        }
        if ($request->has('external_links')) {
            $data['external_links'] = array_slice($linksForStorage, 0, 3);
        }

        $data = array_merge($data, $this->resolveHeaderBackground($request, $user));
        $data = array_merge($data, $this->resolveProfilePicture($request, $user, $data));

        $user->update($data);

        Log::channel('audit_trail')->info('[API] [PROFILE] Updated.', ['user_id' => $user->id]);

        return $this->ok(new UserResource($user->fresh()));
    }

    /**
     * POST /me/change-password
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $request->user()->update(['password' => Hash::make($request->new_password)]);

        return $this->message(__('messages.password_changed_successfully'));
    }

    /**
     * POST /me/password — set a password for an account that has none (social-only).
     */
    public function setPassword(SetPasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        if ($user->password) {
            return $this->error(__('messages.error_password_already_set'), 409, 'password_already_set');
        }

        $user->update(['password' => Hash::make($request->password)]);

        return $this->message(__('messages.password_set_successfully'));
    }

    /**
     * DELETE /me/password
     */
    public function removePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->password) {
            return $this->error('No password is set.', 409, 'no_password');
        }
        if ($user->getActiveAuthMethodsCount() <= 1) {
            return $this->error(__('messages.error_cannot_unlink_last_auth'), 409, 'last_auth_method');
        }

        $user->forceFill(['password' => null])->save();

        return $this->message('Password removed.');
    }

    /**
     * POST /me/profile-picture/generate — AI-generated avatar.
     */
    public function generatePicture(GeneratePictureRequest $request): JsonResponse
    {
        $user = $request->user();
        $today = Carbon::today();
        $last = $user->last_ai_generation_date ? Carbon::parse($user->last_ai_generation_date) : null;

        if ($last) {
            if (! $last->isSameMonth($today)) {
                $user->ai_generations_monthly_count = 0;
            }
            if (! $last->isSameDay($today)) {
                $user->ai_generations_daily_count = 0;
            }
        }

        if ($user->ai_generations_monthly_count >= 5) {
            return $this->error('You have reached your monthly generation limit of 5.', 429, 'rate_limit_exceeded');
        }
        if ($user->ai_generations_daily_count >= 2) {
            return $this->error('You have reached your daily generation limit of 2.', 429, 'rate_limit_exceeded');
        }

        $lang = $user->locale ?? Config::get('app.locale');
        if (! $this->moderation->moderateText($request->prompt, $lang)['is_appropriate']) {
            return $this->error('The provided prompt is inappropriate.', 422, 'content_rejected');
        }

        try {
            $imageContent = $this->imageGeneration->generateImageFromPrompt($request->prompt);
            $this->deleteOwnedFile($user->profile_picture);
            $path = $this->storeProfileImageFromData($imageContent, Str::slug($user->username).'_aigen_'.time());

            $user->profile_picture = $path;
            $user->ai_generations_monthly_count += 1;
            $user->ai_generations_daily_count += 1;
            $user->last_ai_generation_date = $today->toDateString();
            $user->save();

            return $this->ok([
                'profile_picture' => Storage::disk('public')->url($path),
                'monthly_remaining' => 5 - $user->ai_generations_monthly_count,
                'daily_remaining' => 2 - $user->ai_generations_daily_count,
            ]);
        } catch (\Throwable $e) {
            Log::error('API AI picture generation failed: '.$e->getMessage());

            return $this->error('Image generation failed.', 500, 'server_error');
        }
    }

    /**
     * GET /users/check-username?username=
     */
    public function checkUsername(Request $request): JsonResponse
    {
        $username = (string) $request->input('username');

        if (strlen($username) < 5 || strlen($username) > 24
            || ! preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $username)
            || preg_match('/(.)\1{3,}/', $username)) {
            return $this->ok(['available' => false, 'message' => __('validation.regex', ['attribute' => 'username'])]);
        }

        $query = User::where('username', $username);
        if ($request->user()) {
            $query->where('id', '!=', $request->user()->id);
        }

        if ($query->exists()) {
            return $this->ok(['available' => false, 'message' => __('messages.username_taken')]);
        }

        if (! $this->moderation->moderateText($username, Config::get('app.locale'))['is_appropriate']) {
            return $this->ok(['available' => false, 'message' => __('messages.username_inappropriate')]);
        }

        return $this->ok(['available' => true, 'message' => __('messages.username_available')]);
    }

    /**
     * DELETE /me — deactivate (soft-delete + anonymise) the account.
     */
    public function deactivate(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->original_first_name = $user->first_name;
        $user->original_last_name = $user->last_name;
        $user->original_email = $user->email;
        $user->original_profile_picture = $user->profile_picture;

        $user->first_name = 'Deactivated';
        $user->last_name = 'User';
        $user->email = $user->id.'_'.time().'@deleted.user';
        $user->header_background = null;
        $user->external_links = [];
        $user->show_voted_posts_publicly = false;
        $user->profile_picture = 'avatars/goat_ghost_'.rand(1, 8).'.png';
        $user->save();

        // Invalidate every token before soft-deleting.
        $this->tokens->revokeAllRefreshTokens($user);
        $user->tokens()->delete();

        $user->delete();

        Log::channel('audit_trail')->notice('[API] [DEACTIVATE] Account deactivated.', ['user_id' => $user->id]);

        return $this->message(__('messages.account_deactivated_successfully'));
    }

    /**
     * DELETE /me/social/{provider} — unlink a social provider.
     */
    public function unlinkSocial(Request $request, string $provider): JsonResponse
    {
        if (! in_array($provider, ['google', 'x', 'telegram', 'github'], true)) {
            return $this->error('Unsupported provider.', 404, 'not_found');
        }

        $user = $request->user();
        $column = "{$provider}_id";

        if (is_null($user->{$column})) {
            return $this->error('This account is not linked.', 409, 'not_linked');
        }
        if ($user->getActiveAuthMethodsCount() <= 1) {
            return $this->error(__('messages.error_cannot_unlink_last_auth'), 409, 'last_auth_method');
        }

        $user->forceFill([$column => null])->save();

        return $this->ok([
            'message' => ucfirst($provider).' account unlinked.',
            'user' => (new UserResource($user->fresh()))->resolve($request),
        ]);
    }

    /**
     * GET /me/export — full personal-data export (GDPR-style).
     */
    public function export(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->ok([
            'meta' => [
                'export_date' => now()->toIso8601String(),
                'version' => '1.0',
                'issuer' => config('app.name'),
            ],
            'identity' => [
                'username' => $user->username,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'locale' => $user->locale,
                'joined_at' => $user->created_at?->toIso8601String(),
                'verified_at' => $user->email_verified_at?->toIso8601String(),
            ],
            'linked_accounts' => [
                'google' => (bool) $user->google_id,
                'x' => (bool) $user->x_id,
                'telegram' => (bool) $user->telegram_id,
                'github' => (bool) $user->github_id,
            ],
            'posts' => $user->posts()->get(['id', 'question', 'total_votes', 'shares_count', 'created_at']),
            'votes_history' => $user->votes()->with('post:id,question')->get()->map(fn ($v) => [
                'post_id' => $v->post_id,
                'post' => $v->post ? Str::limit($v->post->question, 80) : 'Deleted Post',
                'selection' => $v->vote_option,
                'voted_at' => $v->created_at?->toIso8601String(),
            ]),
            'comments_history' => $user->comments()->with('post:id,question')->get()->map(fn ($c) => [
                'id' => $c->id,
                'post_id' => $c->post_id,
                'content' => $c->content,
                'created_at' => $c->created_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * POST /me/heartbeat — keep the "online" presence timestamp fresh.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->last_active_at = now();
        $user->save();

        return $this->message('ok');
    }

    // --- private helpers ---

    private function resolveHeaderBackground(Request $request, User $user): array
    {
        $oldHeader = $user->header_background;
        $isCustom = $oldHeader && ! Str::startsWith($oldHeader, 'template_');

        if ($request->boolean('remove_header_background')) {
            if ($isCustom) {
                $this->deleteOwnedFile($oldHeader);
            }

            return ['header_background' => null];
        }

        if ($request->hasFile('header_background_upload')) {
            if ($isCustom) {
                $this->deleteOwnedFile($oldHeader);
            }

            return ['header_background' => $this->storeHeaderImage(
                $request->file('header_background_upload'),
                'user_'.$user->id.'_'.time()
            )];
        }

        if ($request->filled('header_background_template')) {
            if ($isCustom) {
                $this->deleteOwnedFile($oldHeader);
            }

            return ['header_background' => $request->input('header_background_template')];
        }

        return [];
    }

    private function resolveProfilePicture(Request $request, User $user, array $data): array
    {
        $first = $data['first_name'] ?? $user->first_name;
        $last = $data['last_name'] ?? $user->last_name;

        if ($request->hasFile('profile_picture')) {
            $this->deleteOwnedFile($user->profile_picture);

            return ['profile_picture' => $this->storeProfileImage(
                $request->file('profile_picture'),
                Str::slug(($data['username'] ?? $user->username)).'_'.$user->id.'_'.time()
            )];
        }

        if ($request->boolean('remove_profile_picture')) {
            $this->deleteOwnedFile($user->profile_picture);

            return ['profile_picture' => $this->avatarService->generateInitialsAvatar($user->id, $first, $last ?? '')];
        }

        return [];
    }

    private function deleteOwnedFile(?string $path): void
    {
        if ($path && ! filter_var($path, FILTER_VALIDATE_URL) && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function storeProfileImage(UploadedFile $file, string $baseFilename): string
    {
        $image = (new ImageManager(new GdDriver))->read($file->getRealPath());
        $image->coverDown(self::PROFILE_IMAGE_SIZE, self::PROFILE_IMAGE_SIZE);
        $path = 'profile_pictures/'.$baseFilename.'.webp';
        Storage::disk('public')->put($path, $image->encode(new WebpEncoder(quality: self::PROFILE_IMAGE_QUALITY)));

        return $path;
    }

    private function storeProfileImageFromData(string $imageData, string $baseFilename): string
    {
        $image = (new ImageManager(new GdDriver))->read($imageData);
        $image->coverDown(self::PROFILE_IMAGE_SIZE, self::PROFILE_IMAGE_SIZE);
        $path = 'profile_pictures/'.$baseFilename.'.webp';
        Storage::disk('public')->put($path, $image->encode(new WebpEncoder(quality: self::PROFILE_IMAGE_QUALITY)));

        return $path;
    }

    private function storeHeaderImage(UploadedFile $file, string $baseFilename): string
    {
        $image = (new ImageManager(new GdDriver))->read($file->getRealPath());
        $image->scaleDown(width: self::HEADER_IMAGE_WIDTH);
        $path = 'header_backgrounds/'.$baseFilename.'.webp';
        Storage::disk('public')->put($path, $image->encode(new WebpEncoder(quality: self::HEADER_IMAGE_QUALITY)));

        return $path;
    }
}
