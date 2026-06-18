<?php

namespace App\Http\Controllers;

use App\Events\PostCreated;
use App\Jobs\PingSearchEngines;
use App\Jobs\SharePostToSocialMedia;
use App\Models\Post;
use App\Models\User;
use App\Models\Vote;
use App\Services\GoatSearchClient;
use App\Services\LevenshteinService;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

class PostController extends Controller
{
    private const MAX_POST_IMAGE_WIDTH = 1024;
    private const MAX_POST_IMAGE_HEIGHT = 1024;
    private const POST_IMAGE_QUALITY = 75;
    private const LQIP_QUALITY = 30;
    private const LQIP_WIDTH = 24;

    private const MAX_POST_IMAGE_SIZE_KB = 2048;
    private const MAX_POST_IMAGE_SIZE_MB = self::MAX_POST_IMAGE_SIZE_KB / 1024;

    private LevenshteinService $levenshteinService;
    private GoatSearchClient $searchEngine;
    private \App\Services\PostMediaService $postMediaService;
    private \App\Services\PostEnrichmentService $postEnrichmentService;
    private \App\Services\ModerationService $moderationService;

    public function __construct(LevenshteinService $levenshteinService, GoatSearchClient $searchEngine, \App\Services\PostMediaService $postMediaService, \App\Services\PostEnrichmentService $postEnrichmentService, \App\Services\ModerationService $moderationService)
    {
        $this->levenshteinService = $levenshteinService;
        $this->searchEngine = $searchEngine;
        $this->postMediaService = $postMediaService;
        $this->postEnrichmentService = $postEnrichmentService;
        $this->moderationService = $moderationService;

    }

    final public function store(Request $request): RedirectResponse
    {
        Log::emergency('[CHECK] 1. CONTROLLER HIT');
        // 1. Validation
        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:255',
            'option_one_title' => 'required|string|max:40',
            'option_one_image' => 'required|image|mimes:jpeg,png,jpg,webp|max:' . self::MAX_POST_IMAGE_SIZE_KB,
            'option_two_title' => 'required|string|max:40',
            'option_two_image' => 'required|image|mimes:jpeg,png,jpg,webp|max:' . self::MAX_POST_IMAGE_SIZE_KB,
        ]);


        if ($validator->fails()) {
            Log::emergency('[CHECK] 2. VALIDATION FAILED: ' . json_encode($validator->errors()));
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $generatedContext = null;
        $generatedTags = null;

        // 2. Check Local Banned Words
        if ($violation = $this->postEnrichmentService->checkLocalBannedWords($request->all())) {
            return redirect()->back()->withErrors($violation)->withInput();
        }

        // 3. TEXT MASTER TASK (DeepSeek): moderation + context + tags
        if ($this->postEnrichmentService->isConfigured()) {
            Log::emergency('[CHECK] 3. STARTING TEXT MODERATION CHECK');
            $groqResult = $this->postEnrichmentService->analyzeText(
                $request->question,
                $request->option_one_title,
                $request->option_two_title
            );

            if (!$groqResult['is_safe']) {
                $field = $groqResult['violation_field'] ?? 'question';
                $errorMsg = $groqResult['moderation_reason'] ?? __('messages.error_post_content_inappropriate');
                return redirect()->back()->withErrors([$field => $errorMsg])->withInput();
            }

            $generatedContext = $groqResult['generated_context'] ?? null;
            $generatedTags = $groqResult['generated_tags'] ?? null;
        }

        // 4. IMAGE MODERATION (Groq vision, context-aware)
        if ($this->postEnrichmentService->imageModerationConfigured()) {
            $imageCheck = $this->postEnrichmentService->moderateImages(
                $request->file('option_one_image'),
                $request->file('option_two_image'),
                $request->all()
            );

            if (!$imageCheck['safe']) {
                $field = $imageCheck['violation_source'] === 'image_two' ? 'option_two_image' : 'option_one_image';
                $reason = $imageCheck['reason'] ?? __('messages.error_post_image_inappropriate');
                return redirect()->back()->withErrors([$field => $reason])->withInput();
            }
        }

        // 5. Image Processing (Local)
        $opt1Paths = $this->processAndStoreImage($request->file('option_one_image'), 'post_images', uniqid('p1_'));
        $opt2Paths = $this->processAndStoreImage($request->file('option_two_image'), 'post_images', uniqid('p2_'));

        if (!$opt1Paths || !$opt2Paths) {
            return redirect()->back()->withErrors(['general' => 'Image processing failed.'])->withInput();
        }

        // 6. Create Post
        $post = Post::create([
            'user_id' => Auth::id(),
            'question' => $request->question,
            'slug' => Str::slug($request->question),
            'option_one_title' => $request->option_one_title,
            'option_one_image' => $opt1Paths['main'],
            'option_one_image_lqip' => $opt1Paths['lqip'],
            'option_two_title' => $request->option_two_title,
            'option_two_image' => $opt2Paths['main'],
            'option_two_image_lqip' => $opt2Paths['lqip'],
            'ai_generated_context' => $generatedContext,
            'ai_generated_tags' => $generatedTags,
        ]);

        // 7. Search Indexing
        if ($generatedTags) {
            try {
                $searchContent = $post->question . ' ' . $post->option_one_title . ' ' . $post->option_two_title . ' ' . $generatedTags;
                $this->searchEngine->index($post->id, $searchContent);
            } catch (Exception $e) {
                Log::error("Indexing failed: " . $e->getMessage());
            }
        }

        Log::emergency('[CHECK] 4. DISPATCHING JOB');
        Log::emergency('[CHECK] DISPATCHING SOCIAL SHARE FOR POST ID: ' . $post->id);
//        SharePostToSocialMedia::dispatch($post)->delay(now()->addSeconds(5));
        SharePostToSocialMedia::dispatch($post);
        PingSearchEngines::dispatch();
        PostCreated::dispatch($post);

        return redirect()->route('home')->with('success', __('messages.post_created_successfully'));
    }

    private function processAndStoreImage(UploadedFile $uploadedFile, string $directory, string $baseFilename): array
    {
        // Delegates to the shared service so the web and the mobile API use one
        // identical implementation. Behaviour is unchanged.
        return $this->postMediaService->processAndStore($uploadedFile, $directory, $baseFilename);
    }

    final public function create(): View
    {
        return view('posts.create', [
            'maxFileSizeKB' => self::MAX_POST_IMAGE_SIZE_KB,
            'maxFileSizeMB' => self::MAX_POST_IMAGE_SIZE_MB
        ]);
    }

    final public function index(Request $request): View|JsonResponse
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
                $query->orderByDesc('created_at')->orderByDesc('id');
                break;
        }

        $posts = $query->paginate(15)->withQueryString();
        $this->attachUserVoteStatus($posts);

        if ($request->ajax()) {
            $html = view('partials.posts-list', ['posts' => $posts])->render();

            return response()->json([
                'html' => $html,
                'hasMorePages' => $posts->hasMorePages()
            ]);
        }

        return view('home', compact('posts'));
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

    final public function show(Post $post): View
    {
        $post->load('user:id,username,profile_picture');
        $post->loadCount(['comments', 'shares as shares_relation_count']);

        $paginatorForOnePost = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([$post]),
            1,
            1,
            1
        );

        $this->attachUserVoteStatus($paginatorForOnePost);

        return view('posts.show', compact('post'));
    }

    final public function edit(Post $post): View|RedirectResponse
    {
        if (Auth::id() !== $post->user_id) {
            abort(403, __('messages.error_unauthorized_action'));
        }
        if ($post->total_votes > 0) {
            return redirect()->route('profile.show', ['username' => Auth::user()->username])
                ->with('error', __('messages.error_cannot_edit_voted_post'));
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
        if ((int)$user->id !== (int)$post->user_id) {
            Log::channel('audit_trail')->warning('[POST] [UPDATE] Unauthorized post update attempt.', [
                'user_id' => $user->id,
                'username' => $user->username,
                'post_id' => $post->id,
                'post_owner_id' => $post->user_id,
                'ip_address' => $request->ip(),
            ]);
            abort(403, __('messages.error_unauthorized_action'));
        }
        if ($post->total_votes > 0) {
            return redirect()->route('profile.show', ['username' => $user->username])
                ->with('error', __('messages.error_cannot_update_voted_post'));
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
            'option_one_image.max' => __('messages.validation_option_one_image_max', ['maxMB' => self::MAX_POST_IMAGE_SIZE_MB]),
            'option_two_image.max' => __('messages.validation_option_two_image_max', ['maxMB' => self::MAX_POST_IMAGE_SIZE_MB]),
            'option_one_image.uploaded' => __('messages.validation_option_one_image_uploaded', ['maxMB' => self::MAX_POST_IMAGE_SIZE_MB]),
            'option_two_image.uploaded' => __('messages.validation_option_two_image_uploaded', ['maxMB' => self::MAX_POST_IMAGE_SIZE_MB]),
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
        $moderationErrorField = null;

        // --- Moderation Stage for Update (Text content) ---
        $textFieldsToModerate = [
            'question' => $request->question,
            'option_one_title' => $request->option_one_title,
            'option_two_title' => $request->option_two_title,
        ];
        $originalPostData = [
            'question' => $post->question,
            'option_one_title' => $post->option_one_title,
            'option_two_title' => $post->option_two_title,
        ];

        $moderationLanguageCode = App::getLocale();

        foreach ($textFieldsToModerate as $field => $newContent) {
            if ($newContent !== $originalPostData[$field]) {
                // 1. Banned Words Check (local)
                $bannedWordCheck = $this->checkForBannedWords($newContent, $field);
                if ($bannedWordCheck && !$bannedWordCheck['is_appropriate']) {
                    $moderationErrorMessage = __($bannedWordCheck['translation_key'], $bannedWordCheck['translation_params']);
                    Log::channel('audit_trail')->info('[POST] [UPDATE] Post update rejected by local blacklist.', array_merge($logContextBase, ['field' => $field, 'reason_key' => $bannedWordCheck['translation_key'], 'category' => $bannedWordCheck['category']]));
                    return redirect()->back()->withErrors([$field => $moderationErrorMessage])->withInput();
                }

                // 2. Text Moderation (DeepSeek)
                $textCheck = $this->moderationService->moderateText($newContent, $moderationLanguageCode);
                if (!$textCheck['is_appropriate']) {
                    $reasonText = $textCheck['reason'] ?? $textCheck['category'];
                    $moderationErrorMessage = __('messages.error_post_content_inappropriate', ['field' => $field, 'reason' => $reasonText]);
                    Log::channel('audit_trail')->info('[POST] [UPDATE] Post update rejected by text moderation.', array_merge($logContextBase, ['field' => $field, 'reason' => $reasonText, 'category' => $textCheck['category']]));
                    return redirect()->back()->withErrors([$field => $moderationErrorMessage])->withInput();
                }
            }
        }

        // --- Image Moderation for new/replaced images ---
        $imagesToModerateOnUpdate = [];
        if ($request->hasFile('option_one_image')) $imagesToModerateOnUpdate['option_one_image'] = $request->file('option_one_image');
        if ($request->hasFile('option_two_image')) $imagesToModerateOnUpdate['option_two_image'] = $request->file('option_two_image');

        foreach ($imagesToModerateOnUpdate as $field => $imageFile) {
            // Image moderation (Groq vision)
            $imageCheck = $this->moderationService->moderateImage($imageFile, $moderationLanguageCode);
            if (!$imageCheck['is_appropriate']) {
                $reasonText = $imageCheck['reason'] ?? $imageCheck['category'];
                $moderationErrorMessage = __('messages.error_post_image_inappropriate', ['field' => $field, 'reason' => $reasonText]);
                Log::channel('audit_trail')->info('[POST] [UPDATE] Post update rejected by image moderation.', array_merge($logContextBase, ['field' => $field, 'reason' => $reasonText, 'category' => $imageCheck['category']]));
                return redirect()->back()->withErrors([$field => $moderationErrorMessage])->withInput();
            }
        }
        // --- End Moderation Stage for Update ---

        if ($request->boolean('remove_option_one_image') && $post->option_one_image) {
            Storage::disk('public')->delete($post->option_one_image);
            $data['option_one_image'] = null;
            $data['option_one_image_lqip'] = null;
        } elseif ($request->hasFile('option_one_image')) {
            if ($post->option_one_image) Storage::disk('public')->delete($post->option_one_image);
            $paths = $this->processAndStoreImage($request->file('option_one_image'), 'post_images', uniqid('post_opt1_'));
            $data['option_one_image'] = $paths['main'];
            $data['option_one_image_lqip'] = $paths['lqip'];
        }

        if ($request->boolean('remove_option_two_image') && $post->option_two_image) {
            Storage::disk('public')->delete($post->option_two_image);
            $data['option_two_image'] = null;
            $data['option_two_image_lqip'] = null;
        } elseif ($request->hasFile('option_two_image')) {
            if ($post->option_two_image) Storage::disk('public')->delete($post->option_two_image);
            $paths = $this->processAndStoreImage($request->file('option_two_image'), 'post_images', uniqid('post_opt2_'));
            $data['option_two_image'] = $paths['main'];
            $data['option_two_image_lqip'] = $paths['lqip'];
        }

        $post->update($data);
        Log::channel('audit_trail')->info('[POST] [UPDATE] Post updated and passed all moderation.', array_merge($logContextBase, ['updated_fields' => array_keys($data)]));
        PingSearchEngines::dispatch();
        return redirect()->route('profile.show', ['username' => $post->user->username])->with('success', __('messages.post_updated_successfully'));
    }

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
                    'translation_key' => 'messages.error_post_content_prohibited_language_in_field',
                    'translation_params' => ['field' => $contextLabel],
                    'category' => 'LOCAL_POLICY_VIOLATION',
                    'error' => null
                ];
            }
        }
        return null;
    }

    final public function destroy(Post $post): RedirectResponse
    {
        $user = Auth::user();

        if (!$user || (int)$user->id !== (int)$post->user_id) {
            abort(403, __('messages.error_unauthorized_action'));
        }

        $postOwnerUsername = $post->user->username;
        $previousUrl = url()->previous();
        $profileUrlOfPostOwner = route('profile.show', ['username' => $postOwnerUsername]);

        $filesToDelete = [];
        if ($post->option_one_image) $filesToDelete[] = $post->option_one_image;
        if ($post->option_one_image_lqip) $filesToDelete[] = $post->option_one_image_lqip;
        if ($post->option_two_image) $filesToDelete[] = $post->option_two_image;
        if ($post->option_two_image_lqip) $filesToDelete[] = $post->option_two_image_lqip;

        if (!empty($filesToDelete)) {
            Storage::disk('public')->delete($filesToDelete);
        }
        Vote::where('post_id', $post->id)->delete();
        $post->delete();

        try {
            Log::channel('audit_trail')->info('[POST] [DELETE] Post deleted.', [
                'deleter_user_id' => $user->id,
                'deleted_post_id' => $post->id,
                'ip_address' => request()->ip(),
            ]);

            PingSearchEngines::dispatch();
        } catch (Exception $e) {
            Log::error('Post deleted, but cleanup jobs failed: ' . $e->getMessage());
        }

        if (str_contains($previousUrl, $profileUrlOfPostOwner)) {
            return redirect()->route('profile.show', ['username' => $postOwnerUsername])
                ->with('success', __('messages.post_deleted_successfully'));
        }

        return redirect()->route('home')->with('success', __('messages.post_deleted_successfully'));
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
                'error' => __('messages.error_already_voted'),
                'message' => __('messages.error_already_voted'),
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

        Log::channel('audit_trail')->info('[POST] [VOTE] User voted on post.', [
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
        $post->increment('total_votes');

        $post->refresh();

        return response()->json([
            'message' => __('messages.vote_registered_successfully'),
            'option_one_votes' => $post->option_one_votes,
            'option_two_votes' => $post->option_two_votes,
            'total_votes' => $post->total_votes,
            'user_vote' => $request->option,
        ]);
    }

    public function showBySlug(Request $request, $id, $slug = null)
    {
        $post = Post::findOrFail($id);

        $position = Post::query()
                ->where(function ($query) use ($post) {
                    $query->where('created_at', '>', $post->created_at)
                        ->orWhere(function ($subQuery) use ($post) {
                            $subQuery->where('created_at', $post->created_at)
                                ->where('id', '>', $post->id);
                        });
                })
                ->count() + 1;

        $perPage = 15;
        $page = ceil($position / $perPage);
        if ($page < 1) $page = 1;

        $redirect = redirect()->route('home', ['page' => $page])
            ->with('scrollToPost', $id);

        if ($request->has('comment') && $request->input('comment') > 0) {
            $redirect->with('scrollToComment', $request->input('comment'));
        }

        return $redirect;
    }

    public function incrementShareCount(Request $request, Post $post)
    {
        $post->increment('shares_count');
        $user = Auth::user();
        Log::channel('audit_trail')->info('[POST] [SHARE] Post share count incremented.', [
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
        $perPage = 15;

        if (!$queryTerm) {
            $emptyParams = ['path' => Paginator::resolveCurrentPath()];
            $posts = new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage, 1, $emptyParams);

            return view('search.results', [
                'posts' => $posts,
                'users' => collect(),
                'queryTerm' => null
            ]);
        }

        $soundexCode = soundex($queryTerm);
        $candidateUsers = User::query()
            ->where(function (Builder $subQuery) use ($queryTerm, $soundexCode) {
                $subQuery->where('username', 'LIKE', "%{$queryTerm}%")
                    ->orWhereRaw('SOUNDEX(username) = ?', [$soundexCode])
                    ->orWhere('first_name', 'LIKE', "%{$queryTerm}%")
                    ->orWhereRaw('SOUNDEX(first_name) = ?', [$soundexCode]);
            })
            ->limit(50)
            ->get();

        $sortedUsers = $this->levenshteinService->findBestMatches(
            $queryTerm,
            $candidateUsers,
            ['username', 'first_name', 'last_name']
        );
        $users = $sortedUsers->take(10);

        $posts = null;
        try {
            $ids = $this->searchEngine->search($queryTerm);

            if (!empty($ids)) {
                $idsString = implode(',', $ids);

                $posts = Post::query()->withPostData()
                    ->whereIn('id', $ids)
                    ->orderByRaw("FIELD(id, $idsString)")
                    ->paginate($perPage);
            } else {
                $posts = new \Illuminate\Pagination\LengthAwarePaginator(
                    collect([]),
                    0,
                    $perPage,
                    1,
                    ['path' => Paginator::resolveCurrentPath(), 'query' => $request->query()]
                );
            }
        } catch (Exception $e) {
            Log::error("Goat Engine Search Failed: " . $e->getMessage());

            $posts = new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage, 1);
        }

        $this->attachUserVoteStatus($posts);

        if ($request->ajax()) {
            $html = view('partials.posts-list', ['posts' => $posts])->render();
            return response()->json([
                'html' => $html,
                'hasMorePages' => $posts->hasMorePages()
            ]);
        }

        return view('search.results', [
            'posts' => $posts,
            'users' => $users,
            'queryTerm' => $queryTerm
        ]);
    }

    final public function loadMorePosts(Request $request): JsonResponse
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
                $query->orderByDesc('created_at')->orderByDesc('id');
                break;
        }

        $posts = $query->paginate(15)->withQueryString();
        $this->attachUserVoteStatus($posts);

        $html = view('partials.posts-list', ['posts' => $posts])->render();

        return response()->json([
            'html' => $html,
            'hasMorePages' => $posts->hasMorePages()
        ]);
    }

    final public function showUserPost(string $username, Post $post)
    {
        $post->load(
            'user:id,username,profile_picture',
            'comments.user',
            'comments.likes'
        );

        $post->loadCount('comments');

        if ($post->user->username !== $username) {
            return redirect()->route('posts.show.user-scoped', [
                'username' => $post->user->username,
                'post' => $post->id
            ], 301);
        }

        $paginatorForOnePost = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([$post]), 1, 1, 1
        );
        $this->attachUserVoteStatus($paginatorForOnePost);

        return view('posts.show', compact('post'));
    }

}
