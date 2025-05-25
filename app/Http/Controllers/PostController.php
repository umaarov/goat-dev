<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Vote;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

class PostController extends Controller
{
    private const MAX_POST_IMAGE_WIDTH = 1024;
    private const MAX_POST_IMAGE_HEIGHT = 1024;
    private const POST_IMAGE_QUALITY = 75;

    private const MAX_POST_IMAGE_SIZE_KB = 2048;
    private const MAX_POST_IMAGE_SIZE_MB = self::MAX_POST_IMAGE_SIZE_KB / 1024;

    private function checkForBannedWords(string $textContent, string $contextLabel): ?array
    {
        $bannedWordsString = Config::get('gemini.banned_words_uz');
        if (empty($bannedWordsString)) {
            return null;
        }
        $bannedWords = array_filter(array_map('trim', explode(',', strtolower($bannedWordsString))));
        if (empty($bannedWords)) {
            return null;
        }

        $lowerCommentContent = strtolower($textContent);
        foreach ($bannedWords as $word) {
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/u', $lowerCommentContent)) {
                Log::info('Post content flagged by local blacklist.', [
                    'context' => $contextLabel,
                    'flagged_word' => $word,
                    'content_snippet' => Str::limit($textContent, 100),
                ]);
                return [
                    'is_appropriate' => false,
                    'reason' => "Content in '{$contextLabel}' contains prohibited language.",
                    'category' => 'LOCAL_POLICY_VIOLATION',
                    'error' => null
                ];
            }
        }
        return null;
    }

    private function moderateTextWithGemini(string $textContent, string $contextLabel): array
    {
        $apiKey = Config::get('gemini.api_key');
        $promptTemplate = Config::get('gemini.prompt_template');

        if (!$apiKey || !$promptTemplate) {
            Log::error('Gemini text moderation config missing.', ['context' => $contextLabel]);
            return ['is_appropriate' => true, 'reason' => null, 'category' => 'UNCHECKED_CONFIG_ERROR', 'error' => 'Gemini config missing. Content allowed.'];
        }

        $model = Config::get('gemini.model', 'gemini-2.0-flash');
        $apiUrl = rtrim(Config::get('gemini.api_url'), '/') . '/' . $model . ':generateContent?key=' . $apiKey;
        $finalPrompt = str_replace("{COMMENT_TEXT}", addslashes($textContent), $promptTemplate);

        $payload = ['contents' => [['parts' => [['text' => $finalPrompt]]]], 'generationConfig' => ['responseMimeType' => 'application/json']];

        try {
            $response = Http::timeout(15)->post($apiUrl, $payload);
            if (!$response->successful()) {
                Log::error('Gemini text API request failed.', ['status' => $response->status(), 'body' => $response->body(), 'context' => $contextLabel]);
                return ['is_appropriate' => false, 'reason' => 'Text moderation service unavailable.', 'category' => 'ERROR_API_REQUEST', 'error' => 'API request failed'];
            }
            $responseData = $response->json();
            $jsonString = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if (!$jsonString) {
                Log::error('Gemini text API response format error.', ['response' => $responseData, 'context' => $contextLabel]);
                return ['is_appropriate' => false, 'reason' => 'Text moderation service error.', 'category' => 'ERROR_API_RESPONSE_FORMAT', 'error' => 'Malformed API response'];
            }
            $moderationResult = json_decode($jsonString, true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($moderationResult['is_appropriate'])) {
                Log::error('Gemini text API response parsing error.', ['json_string' => $jsonString, 'context' => $contextLabel]);
                return ['is_appropriate' => false, 'reason' => 'Text moderation service response error.', 'category' => 'ERROR_API_RESPONSE_PARSE', 'error' => 'Failed to parse result'];
            }
            return ['is_appropriate' => (bool)$moderationResult['is_appropriate'], 'reason' => $moderationResult['reason_if_inappropriate'] ?? null, 'category' => $moderationResult['violation_category'] ?? 'UNKNOWN', 'error' => null];
        } catch (Exception $e) {
            Log::error('Gemini text moderation exception.', ['message' => $e->getMessage(), 'context' => $contextLabel]);
            return ['is_appropriate' => false, 'reason' => 'Unexpected error during text moderation.', 'category' => 'ERROR_UNKNOWN', 'error' => $e->getMessage()];
        }
    }

    private function moderateImageWithGemini(UploadedFile $imageFile, string $contextLabel): array
    {
        $apiKey = Config::get('gemini.api_key');
        $promptTemplate = Config::get('gemini.image_prompt_template');

        if (!$apiKey || !$promptTemplate) {
            Log::error('Gemini image moderation config missing.', ['context' => $contextLabel]);
            return ['is_appropriate' => true, 'reason' => null, 'category' => 'UNCHECKED_CONFIG_ERROR', 'error' => 'Gemini image config missing. Image allowed.'];
        }

        $model = Config::get('gemini.model', 'gemini-2.0-flash');
        $apiUrl = rtrim(Config::get('gemini.api_url'), '/') . '/' . $model . ':generateContent?key=' . $apiKey;

        try {
            $imageData = file_get_contents($imageFile->getRealPath());
            $base64Image = base64_encode($imageData);
            $mimeType = $imageFile->getMimeType();
        } catch (Exception $e) {
            Log::error('Failed to read or encode image for moderation.', ['message' => $e->getMessage(), 'context' => $contextLabel]);
            return ['is_appropriate' => false, 'reason' => 'Failed to process image file for moderation.', 'category' => 'ERROR_FILE_PROCESSING', 'error' => $e->getMessage()];
        }

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $promptTemplate],
                        [
                            'inlineData' => [
                                'mimeType' => $mimeType,
                                'data' => $base64Image
                            ]
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json'
            ]
        ];

        try {
            $response = Http::timeout(30)->post($apiUrl, $payload);
            if (!$response->successful()) {
                Log::error('Gemini image API request failed.', ['status' => $response->status(), 'body' => $response->body(), 'context' => $contextLabel]);
                return ['is_appropriate' => false, 'reason' => 'Image moderation service unavailable.', 'category' => 'ERROR_API_REQUEST', 'error' => 'API request failed'];
            }
            $responseData = $response->json();
            $jsonString = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if (!$jsonString) {
                Log::error('Gemini image API response format error.', ['response' => $responseData, 'context' => $contextLabel]);
                return ['is_appropriate' => false, 'reason' => 'Image moderation service error.', 'category' => 'ERROR_API_RESPONSE_FORMAT', 'error' => 'Malformed API response'];
            }
            $moderationResult = json_decode($jsonString, true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($moderationResult['is_appropriate'])) {
                Log::error('Gemini image API response parsing error.', ['json_string' => $jsonString, 'context' => $contextLabel]);
                return ['is_appropriate' => false, 'reason' => 'Image moderation service response error.', 'category' => 'ERROR_API_RESPONSE_PARSE', 'error' => 'Failed to parse result'];
            }
            return ['is_appropriate' => (bool)$moderationResult['is_appropriate'], 'reason' => $moderationResult['reason_if_inappropriate'] ?? null, 'category' => $moderationResult['violation_category'] ?? 'UNKNOWN_IMAGE', 'error' => null];
        } catch (Exception $e) {
            Log::error('Gemini image moderation exception.', ['message' => $e->getMessage(), 'context' => $contextLabel]);
            return ['is_appropriate' => false, 'reason' => 'Unexpected error during image moderation.', 'category' => 'ERROR_UNKNOWN', 'error' => $e->getMessage()];
        }
    }

    private function processAndStoreImage(UploadedFile $uploadedFile, string $directory, string $baseFilename): string
    {
        $manager = ImageManager::gd();
        $image = $manager->read($uploadedFile->getRealPath());

        $image->scaleDown(self::MAX_POST_IMAGE_WIDTH, self::MAX_POST_IMAGE_HEIGHT);

        $originalExtension = $uploadedFile->getClientOriginalExtension();
        $filename = $baseFilename . '.' . $originalExtension;
        $path = $directory . '/' . $filename;
        $extension = strtolower($originalExtension);

        switch ($extension) {
            case 'jpeg':
            case 'jpg':
                $encodedImage = $image->toJpeg(self::POST_IMAGE_QUALITY)->toString();
                break;
            case 'png':
                $encodedImage = $image->toPng()->toString();
                break;
            case 'gif':
                $encodedImage = $image->toGif()->toString();
                // Storage::disk('public')->put($path, file_get_contents($uploadedFile->getRealPath())); return $path;
                break;
            case 'webp':
                $encodedImage = $image->toWebp(self::POST_IMAGE_QUALITY)->toString();
                break;
            default:
                $newExtension = 'jpg';
                $filename = $baseFilename . '.' . $newExtension;
                $path = $directory . '/' . $filename;
                $encodedImage = $image->toJpeg(self::POST_IMAGE_QUALITY)->toString();
        }
        Storage::disk('public')->put($path, $encodedImage);
        return $path;
    }

    final public function index(Request $request): View
    {
        $query = Post::query()->withPostData();

        switch ($request->input('filter')) {
            case 'trending':
                $query->where('created_at', '>=', now()->subDays(7))
                    ->orderByDesc('total_votes')
                    ->orderByDesc('created_at');
                break;
            case 'latest':
            default:
                $query->latest();
                break;
        }

        $posts = $query->paginate(15)->withQueryString();
        $this->attachUserVoteStatus($posts);
        return view('home', compact('posts'));
    }

    final public function create(): View
    {
        return view('posts.create', [
            'maxFileSizeKB' => self::MAX_POST_IMAGE_SIZE_KB,
            'maxFileSizeMB' => self::MAX_POST_IMAGE_SIZE_MB
        ]);
    }


    final public function store(Request $request): RedirectResponse
    {
        $rules = [
            'question' => 'required|string|max:255',
            'option_one_title' => 'required|string|max:40',
            'option_one_image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:' . self::MAX_POST_IMAGE_SIZE_KB,
            'option_two_title' => 'required|string|max:40',
            'option_two_image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:' . self::MAX_POST_IMAGE_SIZE_KB,
        ];

        $messages = [
            'option_one_image.max' => 'The image for Subject 1 must be ' . self::MAX_POST_IMAGE_SIZE_MB . 'MB or smaller. Please upload a smaller file.',
            'option_two_image.max' => 'The image for Subject 2 must be ' . self::MAX_POST_IMAGE_SIZE_MB . 'MB or smaller. Please upload a smaller file.',
            'option_one_image.uploaded' => 'The image for Subject 1 failed to upload. It might be too large (max ' . self::MAX_POST_IMAGE_SIZE_MB . 'MB) or an unsupported type.',
            'option_two_image.uploaded' => 'The image for Subject 2 failed to upload. It might be too large (max ' . self::MAX_POST_IMAGE_SIZE_MB . 'MB) or an unsupported type.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $user = Auth::user();
        $moderationErrorField = null;
        $moderationErrorMessage = 'Your post could not be created as part of its content violates community guidelines.';
        $logContextBase = [
            'user_id' => $user->id,
            'username' => $user->username,
            'ip_address' => $request->ip(),
        ];

        // --- Moderation Stage ---
        $fieldsToModerate = [
            'question' => $request->input('question'),
            'option_one_title' => $request->input('option_one_title'),
            'option_two_title' => $request->input('option_two_title'),
        ];

        foreach ($fieldsToModerate as $field => $content) {
            // 1. Banned Words Check
            $bannedWordCheck = $this->checkForBannedWords($content, $field);
            if ($bannedWordCheck && !$bannedWordCheck['is_appropriate']) {
                $moderationErrorField = $field;
                $moderationErrorMessage = $bannedWordCheck['reason'] ?? $moderationErrorMessage;
                Log::channel('audit_trail')->info('Post creation rejected by local blacklist.', array_merge($logContextBase, ['field' => $field, 'reason' => $bannedWordCheck['reason'], 'category' => $bannedWordCheck['category'], 'content_snippet' => Str::limit($content, 50)]));
                return redirect()->back()->withErrors([$moderationErrorField => $moderationErrorMessage])->withInput();
            }

            // 2. Gemini Text Check
            $geminiTextCheck = $this->moderateTextWithGemini($content, $field);
            if (!$geminiTextCheck['is_appropriate']) {
                $moderationErrorField = $field;
                $moderationErrorMessage = "Content in '{$field}' was deemed inappropriate. Reason: " . ($geminiTextCheck['reason'] ?? $geminiTextCheck['category']);
                if (str_starts_with($geminiTextCheck['category'], 'ERROR_') || str_starts_with($geminiTextCheck['category'], 'UNCHECKED_')) {
                    $moderationErrorMessage = "Could not verify content for '{$field}' due to a system issue. Please try again.";
                    Log::warning('Gemini Text Moderation Service Error during post creation.', array_merge($logContextBase, ['field' => $field, 'details' => $geminiTextCheck]));
                } else {
                    Log::channel('audit_trail')->info('Post creation rejected by Gemini text moderation.', array_merge($logContextBase, ['field' => $field, 'reason' => $geminiTextCheck['reason'], 'category' => $geminiTextCheck['category'], 'content_snippet' => Str::limit($content, 50)]));
                }
                return redirect()->back()->withErrors([$moderationErrorField => $moderationErrorMessage])->withInput();
            }
        }

        // 3. Gemini Image Checks
        $imagesToModerate = [];
        if ($request->hasFile('option_one_image')) $imagesToModerate['option_one_image'] = $request->file('option_one_image');
        if ($request->hasFile('option_two_image')) $imagesToModerate['option_two_image'] = $request->file('option_two_image');

        foreach ($imagesToModerate as $field => $imageFile) {
            $geminiImageCheck = $this->moderateImageWithGemini($imageFile, $field);
            if (!$geminiImageCheck['is_appropriate']) {
                $moderationErrorField = $field;
                $moderationErrorMessage = "The image for '{$field}' was deemed inappropriate. Reason: " . ($geminiImageCheck['reason'] ?? $geminiImageCheck['category']);
                if (str_starts_with($geminiImageCheck['category'], 'ERROR_') || str_starts_with($geminiImageCheck['category'], 'UNCHECKED_')) {
                    $moderationErrorMessage = "Could not verify image for '{$field}' due to a system issue. Please try again.";
                    Log::warning('Gemini Image Moderation Service Error during post creation.', array_merge($logContextBase, ['field' => $field, 'details' => $geminiImageCheck]));
                } else {
                    Log::channel('audit_trail')->info('Post creation rejected by Gemini image moderation.', array_merge($logContextBase, ['field' => $field, 'reason' => $geminiImageCheck['reason'], 'category' => $geminiImageCheck['category']]));
                }
                return redirect()->back()->withErrors([$moderationErrorField => $moderationErrorMessage])->withInput();
            }
        }
        // --- End Moderation Stage ---

        $optionOneImagePath = null;
        if ($request->hasFile('option_one_image')) {
            $optionOneImagePath = $this->processAndStoreImage($request->file('option_one_image'), 'post_images', uniqid('post_opt1_'));
        }

        $optionTwoImagePath = null;
        if ($request->hasFile('option_two_image')) {
            $optionTwoImagePath = $this->processAndStoreImage($request->file('option_two_image'), 'post_images', uniqid('post_opt2_'));
        }

        $post = Post::create([
            'user_id' => $user->id,
            'question' => $request->question,
            'option_one_title' => $request->option_one_title,
            'option_one_image' => $optionOneImagePath,
            'option_two_title' => $request->option_two_title,
            'option_two_image' => $optionTwoImagePath,
        ]);

        Log::channel('audit_trail')->info('Post created and passed all moderation.', array_merge($logContextBase, [
            'post_id' => $post->id,
            'question' => Str::limit($post->question, 100),
        ]));

        return redirect()->route('home')->with('success', 'Post created successfully!');
    }

    final public function edit(Post $post): View|RedirectResponse
    {
        if (Auth::id() !== $post->user_id) {
            abort(403, 'Unauthorized action.');
        }
        if ($post->total_votes > 0 && !Auth::user()->isAdmin()) {
            return redirect()->route('profile.show', ['username' => Auth::user()->username])
                ->with('error', 'Cannot edit a post that has already received votes.');
        }
        return view('posts.edit', [
            'post' => $post,
            'maxFileSizeKB' => self::MAX_POST_IMAGE_SIZE_KB,
            'maxFileSizeMB' => self::MAX_POST_IMAGE_SIZE_MB
        ]);
    }

    final public function update(Request $request, Post $post): RedirectResponse
    {
        $user = Auth::user();
        if ($user->id !== $post->user_id) {
            Log::channel('audit_trail')->warning('Unauthorized post update attempt.', [ /* ... */]);
            abort(403, 'Unauthorized action.');
        }
        if ($post->total_votes > 0 && !Auth::user()->isAdmin()) {
            return redirect()->route('profile.show', ['username' => Auth::user()->username])
                ->with('error', 'Cannot update a post that has already received votes.');
        }

        $rules = [
            'question' => 'required|string|max:255',
            'option_one_title' => 'required|string|max:40',
            'option_one_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:' . self::MAX_POST_IMAGE_SIZE_KB,
            'option_two_title' => 'required|string|max:40',
            'option_two_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:' . self::MAX_POST_IMAGE_SIZE_KB,
            'remove_option_one_image' => 'nullable|boolean',
            'remove_option_two_image' => 'nullable|boolean',
        ];

        $messages = [
            'option_one_image.max' => 'The new image for Subject 1 must be ' . self::MAX_POST_IMAGE_SIZE_MB . 'MB or smaller. Please upload a smaller file.',
            'option_two_image.max' => 'The new image for Subject 2 must be ' . self::MAX_POST_IMAGE_SIZE_MB . 'MB or smaller. Please upload a smaller file.',
            'option_one_image.uploaded' => 'The image for Subject 1 failed to upload. It might be too large (max ' . self::MAX_POST_IMAGE_SIZE_MB . 'MB) or an unsupported type.',
            'option_two_image.uploaded' => 'The image for Subject 2 failed to upload. It might be too large (max ' . self::MAX_POST_IMAGE_SIZE_MB . 'MB) or an unsupported type.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $request->only(['question', 'option_one_title', 'option_two_title']);
        $logContextBase = [
            'user_id' => $user->id,
            'username' => $user->username,
            'post_id' => $post->id,
            'ip_address' => $request->ip(),
        ];

        // --- Moderation Stage for Update (Text content) ---
        if ($request->question !== $post->question) {
            $bannedWordCheck = $this->checkForBannedWords($request->question, 'question');
            if ($bannedWordCheck && !$bannedWordCheck['is_appropriate']) { /* ... reject ... */
                Log::channel('audit_trail')->info('Post update rejected by local blacklist.', array_merge($logContextBase, ['field' => 'question', 'reason' => $bannedWordCheck['reason']]));
                return redirect()->back()->withErrors(['question' => $bannedWordCheck['reason']])->withInput();
            }
            $geminiTextCheck = $this->moderateTextWithGemini($request->question, 'question');
            if (!$geminiTextCheck['is_appropriate']) { /* ... reject ... */
                $reason = "Content in 'question' was deemed inappropriate. Reason: " . ($geminiTextCheck['reason'] ?? $geminiTextCheck['category']);
                Log::channel('audit_trail')->info('Post update rejected by Gemini text moderation.', array_merge($logContextBase, ['field' => 'question', 'reason' => $reason]));
                return redirect()->back()->withErrors(['question' => $reason])->withInput();
            }
        }
        if ($request->option_one_title !== $post->option_one_title) {
            $bannedWordCheck = $this->checkForBannedWords($request->option_one_title, 'option_one_title');
            if ($bannedWordCheck && !$bannedWordCheck['is_appropriate']) {
                Log::channel('audit_trail')->info('Post update rejected by local blacklist for option_one_title.', array_merge($logContextBase, ['reason' => $bannedWordCheck['reason']]));
                return redirect()->back()->withErrors(['option_one_title' => $bannedWordCheck['reason']])->withInput();
            }
            $geminiTextCheck = $this->moderateTextWithGemini($request->option_one_title, 'option_one_title');
            if (!$geminiTextCheck['is_appropriate']) {
                $reason = "Content in 'option_one_title' was deemed inappropriate. Reason: " . ($geminiTextCheck['reason'] ?? $geminiTextCheck['category']);
                Log::channel('audit_trail')->info('Post update rejected by Gemini text for option_one_title.', array_merge($logContextBase, ['reason' => $reason]));
                return redirect()->back()->withErrors(['option_one_title' => $reason])->withInput();
            }
        }
        if ($request->option_two_title !== $post->option_two_title) {
            $bannedWordCheck = $this->checkForBannedWords($request->option_two_title, 'option_two_title');
            if ($bannedWordCheck && !$bannedWordCheck['is_appropriate']) {
                Log::channel('audit_trail')->info('Post update rejected by local blacklist for option_two_title.', array_merge($logContextBase, ['reason' => $bannedWordCheck['reason']]));
                return redirect()->back()->withErrors(['option_two_title' => $bannedWordCheck['reason']])->withInput();
            }
            $geminiTextCheck = $this->moderateTextWithGemini($request->option_two_title, 'option_two_title');
            if (!$geminiTextCheck['is_appropriate']) {
                $reason = "Content in 'option_two_title' was deemed inappropriate. Reason: " . ($geminiTextCheck['reason'] ?? $geminiTextCheck['category']);
                Log::channel('audit_trail')->info('Post update rejected by Gemini text for option_two_title.', array_merge($logContextBase, ['reason' => $reason]));
                return redirect()->back()->withErrors(['option_two_title' => $reason])->withInput();
            }
        }
        // --- Image Moderation for new/replaced images ---
        if ($request->hasFile('option_one_image')) {
            $geminiImageCheck = $this->moderateImageWithGemini($request->file('option_one_image'), 'option_one_image');
            if (!$geminiImageCheck['is_appropriate']) { /* ... reject ... */
                $reason = "The new image for 'option_one_image' was deemed inappropriate. Reason: " . ($geminiImageCheck['reason'] ?? $geminiImageCheck['category']);
                Log::channel('audit_trail')->info('Post update rejected by Gemini image for option_one_image.', array_merge($logContextBase, ['reason' => $reason]));
                return redirect()->back()->withErrors(['option_one_image' => $reason])->withInput();
            }
        }
        if ($request->hasFile('option_two_image')) {
            $geminiImageCheck = $this->moderateImageWithGemini($request->file('option_two_image'), 'option_two_image');
            if (!$geminiImageCheck['is_appropriate']) { /* ... reject ... */
                $reason = "The new image for 'option_two_image' was deemed inappropriate. Reason: " . ($geminiImageCheck['reason'] ?? $geminiImageCheck['category']);
                Log::channel('audit_trail')->info('Post update rejected by Gemini image for option_two_image.', array_merge($logContextBase, ['reason' => $reason]));
                return redirect()->back()->withErrors(['option_two_image' => $reason])->withInput();
            }
        }


        if ($request->boolean('remove_option_one_image') && $post->option_one_image) {
            Storage::disk('public')->delete($post->option_one_image);
            $data['option_one_image'] = null;
        } elseif ($request->hasFile('option_one_image')) {
            if ($post->option_one_image) Storage::disk('public')->delete($post->option_one_image);
            $data['option_one_image'] = $this->processAndStoreImage($request->file('option_one_image'), 'post_images', uniqid('post_opt1_'));
        }

        if ($request->boolean('remove_option_two_image') && $post->option_two_image) {
            Storage::disk('public')->delete($post->option_two_image);
            $data['option_two_image'] = null;
        } elseif ($request->hasFile('option_two_image')) {
            if ($post->option_two_image) Storage::disk('public')->delete($post->option_two_image);
            $data['option_two_image'] = $this->processAndStoreImage($request->file('option_two_image'), 'post_images', uniqid('post_opt2_'));
        }

        $post->update($data);
        Log::channel('audit_trail')->info('Post updated and passed all moderation.', array_merge($logContextBase, ['updated_fields' => array_keys($data)]));
        return redirect()->route('profile.show', ['username' => $post->user->username])->with('success', 'Post updated successfully.');
    }

    final public function destroy(Post $post): RedirectResponse
    {
        $user = Auth::user();
        if ($user->id !== $post->user_id) {
            Log::channel('audit_trail')->warning('Unauthorized post deletion attempt.', [
                'user_id' => $user->id,
                'username' => $user->username,
                'post_id' => $post->id,
                'post_owner_id' => $post->user_id,
                'ip_address' => request()->ip(),
            ]);
            abort(403, 'Unauthorized action.');
        }

        $postId = $post->id;
        $postQuestion = $post->question;

        if ($post->option_one_image) Storage::disk('public')->delete($post->option_one_image);
        if ($post->option_two_image) Storage::disk('public')->delete($post->option_two_image);

        Vote::where('post_id', $post->id)->delete();

        $post->delete();

        Log::channel('audit_trail')->info('Post deleted.', [
            'user_id' => $user->id,
            'username' => $user->username,
            'deleted_post_id' => $postId,
            'original_post_question' => Str::limit($postQuestion, 100),
            'ip_address' => request()->ip(),
            'deleted_by_admin' => $user->isAdmin() && $user->id !== $post->user_id,
        ]);

        if (str_contains(url()->previous(), route('profile.show', ['username' => $post->user->username]))) {
            return redirect()->route('profile.show', ['username' => $post->user->username])
                ->with('success', 'Post deleted successfully.');
        }
        if (url()->previous() == route('profile.show', ['username' => Auth::user()->username])) {
            return redirect()->route('profile.show', ['username' => Auth::user()->username])
                ->with('success', 'Post deleted successfully.');
        }
        return redirect()->route('home')->with('success', 'Post deleted successfully.');
    }

    final public function vote(Request $request, Post $post): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'option' => 'required|in:option_one,option_two',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $loggedInUserId = Auth::id();

        $existingVote = Vote::where('user_id', $loggedInUserId)
            ->where('post_id', $post->id)
            ->first();

        if ($existingVote) {
            $post->refresh();
            return response()->json([
                'error' => 'You have already voted on this post.',
                'message' => 'You have already voted on this post.',
                'user_vote' => $existingVote->vote_option,
                'option_one_votes' => $post->option_one_votes,
                'option_two_votes' => $post->option_two_votes,
                'total_votes' => $post->total_votes,
            ], 409);
        }

        Vote::create([
            'user_id' => $loggedInUserId,
            'post_id' => $post->id,
            'vote_option' => $request->option,
        ]);

        Log::channel('audit_trail')->info('User voted on post.', [
            'user_id' => $user->id,
            'username' => $user->username,
            'post_id' => $post->id,
            'voted_option' => $request->option,
            'ip_address' => $request->ip(),
        ]);

        if ($request->option === 'option_one') {
            $post->increment('option_one_votes');
        } else {
            $post->increment('option_two_votes');
        }
        // $post->increment('total_votes');

        $post->refresh();

        return response()->json([
            'message' => 'Vote registered successfully!',
            'option_one_votes' => $post->option_one_votes,
            'option_two_votes' => $post->option_two_votes,
            'total_votes' => $post->total_votes,
            'user_vote' => $request->option,
        ]);
    }

    public function showBySlug($id, $slug = null)
    {
        $post = Post::withCount('votes')->findOrFail($id);

        $newerOrSamePostsCount = Post::query()
            ->where('created_at', '>', $post->created_at)
            ->orWhere(function ($query) use ($post) {
                $query->where('created_at', $post->created_at)
                    ->where('id', '>=', $post->id);
            })
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->count();

        $perPage = 15;
        $page = ceil($newerOrSamePostsCount / $perPage);

        $expectedSlug = Str::slug($post->question);
        if ($slug !== null && $slug !== $expectedSlug) {
            return redirect()->route('posts.showSlug', ['id' => $post->id, 'slug' => $expectedSlug, 'page' => $page], 301)->with('scrollToPost', $id);
        }

        return redirect()->route('home', ['page' => $page])
            ->with('scrollToPost', $id);
    }

    public function incrementShareCount(Request $request, Post $post)
    {
        $post->increment('shares_count');
        $user = Auth::user();
        Log::channel('audit_trail')->info('Post share count incremented.', [
            'user_id' => $user ? $user->id : null,
            'username' => $user ? $user->username : 'Guest/Unconfirmed',
            'post_id' => $post->id,
            'ip_address' => $request->ip(),
        ]);
        return response()->json(['shares_count' => $post->shares_count]);
    }

    final public function search(Request $request): View|JsonResponse
    {
        $queryTerm = $request->input('q');

        if (!$queryTerm) {
            if ($request->expectsJson()) {
                return response()->json(['data' => []]);
            }
            $posts = Post::query()->whereRaw('0 = 1')->paginate(15);
            return view('search.results', ['posts' => $posts, 'queryTerm' => null]);
        }

        $query = Post::query()->withPostData();

        $query->where(function (Builder $subQuery) use ($queryTerm) {
            $subQuery->where('question', 'LIKE', "%{$queryTerm}%")
                ->orWhere('option_one_title', 'LIKE', "%{$queryTerm}%")
                ->orWhere('option_two_title', 'LIKE', "%{$queryTerm}%")
                ->orWhereHas('user', function (Builder $userQuery) use ($queryTerm) {
                    $userQuery->where('username', 'LIKE', "%{$queryTerm}%");
                });
        });

        $posts = $query->latest()->paginate(15)->withQueryString();
        $this->attachUserVoteStatus($posts);

        if ($request->expectsJson()) {
            return response()->json($posts);
        }
        return view('search.results', compact('posts', 'queryTerm'));
    }

    private function attachUserVoteStatus(LengthAwarePaginator $posts): void
    {
        $userVoteMap = collect();
        if (Auth::check()) {
            $loggedInUserId = Auth::id();
            $postIds = $posts->pluck('id')->all();

            if (!empty($postIds)) {
                $userVoteMap = Vote::where('user_id', $loggedInUserId)
                    ->whereIn('post_id', $postIds)
                    ->pluck('vote_option', 'post_id');
            }
        }

        $posts->getCollection()->transform(function ($post) use ($userVoteMap) {
            $post->user_vote = $userVoteMap->get($post->id);
            return $post;
        });
    }
}
