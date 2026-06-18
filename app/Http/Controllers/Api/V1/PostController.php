<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\PostCreated;
use App\Http\Requests\Api\V1\ShareRequest;
use App\Http\Requests\Api\V1\StorePostRequest;
use App\Http\Requests\Api\V1\UpdatePostRequest;
use App\Http\Requests\Api\V1\VoteRequest;
use App\Http\Resources\PostResource;
use App\Jobs\PingSearchEngines;
use App\Jobs\SharePostToSocialMedia;
use App\Models\Post;
use App\Models\Share;
use App\Models\User;
use App\Models\Vote;
use App\Services\GoatSearchClient;
use App\Services\ModerationService;
use App\Services\PostEnrichmentService;
use App\Services\PostMediaService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PostController extends ApiController
{
    public function __construct(
        private ModerationService $moderation,
        private PostMediaService $media,
        private GoatSearchClient $search,
        private PostEnrichmentService $enrichment,
    ) {}

    /**
     * GET /posts — the main feed (filter: latest|trending).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Post::query()->withPostData();

        if ($request->input('filter') === 'trending') {
            $query->where('created_at', '>=', now()->subDays(7))
                ->orderByDesc('total_votes')
                ->orderByDesc('created_at');
        } else {
            $query->orderByDesc('created_at')->orderByDesc('id');
        }

        $posts = $query->paginate(15);
        $this->attachUserVotes($posts, $request);

        return $this->paginated($posts, PostResource::class);
    }

    /**
     * GET /posts/search?q=
     */
    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q'));

        if ($term === '') {
            return $this->paginated(
                new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15, 1),
                PostResource::class
            );
        }

        try {
            $ids = $this->search->search($term);
        } catch (\Throwable $e) {
            Log::error('API post search failed: '.$e->getMessage());
            $ids = [];
        }

        if (empty($ids)) {
            return $this->paginated(
                new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15, 1),
                PostResource::class
            );
        }

        $idsString = implode(',', array_map('intval', $ids));
        $posts = Post::query()->withPostData()
            ->whereIn('id', $ids)
            ->orderByRaw("FIELD(id, $idsString)")
            ->paginate(15);

        $this->attachUserVotes($posts, $request);

        return $this->paginated($posts, PostResource::class);
    }

    /**
     * GET /posts/{post}
     */
    public function show(Request $request, Post $post): JsonResponse
    {
        $post->load('user:id,username,first_name,last_name,profile_picture');
        $post->loadCount(['comments', 'shares as shares_relation_count']);
        $post->user_vote = $this->userVoteFor($post->id, $request);

        return $this->ok(new PostResource($post));
    }

    /**
     * POST /posts
     */
    public function store(StorePostRequest $request): JsonResponse
    {
        $user = $request->user();

        $generatedContext = null;
        $generatedTags = null;

        // 1. Cheap local blacklist pre-check.
        if ($violation = $this->enrichment->checkLocalBannedWords($request->all())) {
            $field = array_key_first($violation);

            return $this->error($violation[$field], 422, 'content_rejected', ['errors' => [$field => [$violation[$field]]]]);
        }

        // 2. Groq master task: moderates text AND generates context + tags (mirrors the web).
        if ($this->enrichment->isConfigured()) {
            $analysis = $this->enrichment->analyzeText(
                $request->question,
                $request->option_one_title,
                $request->option_two_title
            );

            if (isset($analysis['is_safe']) && ! $analysis['is_safe']) {
                $field = $analysis['violation_field'] ?? 'question';

                return $this->moderationError($field, ['reason' => $analysis['moderation_reason'] ?? null]);
            }

            $generatedContext = $analysis['generated_context'] ?? null;
            $generatedTags = $analysis['generated_tags'] ?? null;

            // 3. Context-aware vision moderation of both images.
            $imageCheck = $this->enrichment->moderateImages(
                $request->file('option_one_image'),
                $request->file('option_two_image'),
                $request->all()
            );

            if (isset($imageCheck['safe']) && ! $imageCheck['safe']) {
                $field = ($imageCheck['violation_source'] ?? null) === 'image_two' ? 'option_two_image' : 'option_one_image';

                return $this->moderationError($field, ['reason' => $imageCheck['reason'] ?? null]);
            }
        }

        $opt1 = $this->media->processAndStore($request->file('option_one_image'), 'post_images', uniqid('p1_'));
        $opt2 = $this->media->processAndStore($request->file('option_two_image'), 'post_images', uniqid('p2_'));

        $post = Post::create([
            'user_id' => $user->id,
            'question' => $request->question,
            'option_one_title' => $request->option_one_title,
            'option_one_image' => $opt1['main'],
            'option_one_image_lqip' => $opt1['lqip'],
            'option_two_title' => $request->option_two_title,
            'option_two_image' => $opt2['main'],
            'option_two_image_lqip' => $opt2['lqip'],
            'ai_generated_context' => $generatedContext,
            'ai_generated_tags' => $generatedTags,
        ]);

        try {
            $searchContent = "{$post->question} {$post->option_one_title} {$post->option_two_title}";
            if ($generatedTags) {
                $searchContent .= ' '.$generatedTags;
            }
            $this->search->index($post->id, $searchContent);
        } catch (\Throwable $e) {
            Log::error('API post indexing failed: '.$e->getMessage());
        }

        SharePostToSocialMedia::dispatch($post);
        PingSearchEngines::dispatch();
        PostCreated::dispatch($post);

        Log::channel('audit_trail')->info('[API] [POST] [STORE] Post created.', [
            'user_id' => $user->id,
            'post_id' => $post->id,
            'ip_address' => $request->ip(),
        ]);

        $post->load('user:id,username,first_name,last_name,profile_picture')
            ->loadCount(['comments', 'shares as shares_relation_count']);

        return $this->created(new PostResource($post));
    }

    /**
     * PUT /posts/{post}
     */
    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        $user = $request->user();
        if ((int) $user->id !== (int) $post->user_id) {
            return $this->error(__('messages.error_unauthorized_action'), 403, 'access_forbidden');
        }
        if ($post->total_votes > 0) {
            return $this->error(__('messages.error_cannot_update_voted_post'), 409, 'post_already_voted');
        }

        $lang = $user->locale ?? config('app.locale');

        foreach (['question', 'option_one_title', 'option_two_title'] as $field) {
            if ($request->input($field) !== $post->{$field}) {
                $result = $this->moderation->moderateText($request->input($field), $lang);
                if (! $result['is_appropriate']) {
                    return $this->moderationError($field, $result);
                }
            }
        }

        $data = $request->only(['question', 'option_one_title', 'option_two_title']);

        foreach ([['option_one_image', 'remove_option_one_image', 'option_one_image_lqip'],
            ['option_two_image', 'remove_option_two_image', 'option_two_image_lqip']] as [$imageField, $removeField, $lqipField]) {
            if ($request->boolean($removeField) && $post->{$imageField}) {
                $this->media->deletePostMedia($post->{$imageField});
                $data[$imageField] = null;
                $data[$lqipField] = null;
            } elseif ($request->hasFile($imageField)) {
                $result = $this->moderation->moderateImage($request->file($imageField), $lang);
                if (! $result['is_appropriate']) {
                    return $this->moderationError($imageField, $result);
                }
                if ($post->{$imageField}) {
                    $this->media->deletePostMedia($post->{$imageField});
                }
                $paths = $this->media->processAndStore($request->file($imageField), 'post_images', uniqid('post_'));
                $data[$imageField] = $paths['main'];
                $data[$lqipField] = $paths['lqip'];
            }
        }

        $post->update($data);
        PingSearchEngines::dispatch();

        $post->load('user:id,username,first_name,last_name,profile_picture')
            ->loadCount(['comments', 'shares as shares_relation_count']);

        return $this->ok(new PostResource($post));
    }

    /**
     * DELETE /posts/{post}
     */
    public function destroy(Request $request, Post $post): JsonResponse
    {
        $user = $request->user();
        if ((int) $user->id !== (int) $post->user_id) {
            return $this->error(__('messages.error_unauthorized_action'), 403, 'access_forbidden');
        }

        $this->media->deletePostMedia(
            $post->option_one_image,
            $post->option_one_image_lqip,
            $post->option_two_image,
            $post->option_two_image_lqip
        );

        Vote::where('post_id', $post->id)->delete();
        $post->delete();

        PingSearchEngines::dispatch();

        Log::channel('audit_trail')->info('[API] [POST] [DELETE] Post deleted.', [
            'user_id' => $user->id,
            'post_id' => $post->id,
            'ip_address' => $request->ip(),
        ]);

        return $this->message(__('messages.post_deleted_successfully'));
    }

    /**
     * POST /posts/{post}/vote
     */
    public function vote(VoteRequest $request, Post $post): JsonResponse
    {
        $user = $request->user();

        $existing = Vote::where('user_id', $user->id)->where('post_id', $post->id)->first();
        if ($existing) {
            $post->refresh();

            return $this->error(__('messages.error_already_voted'), 409, 'already_voted', [
                'data' => $this->voteState($post, $existing->vote_option),
            ]);
        }

        Vote::create([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'vote_option' => $request->option,
        ]);

        $post->increment($request->option === 'option_one' ? 'option_one_votes' : 'option_two_votes');
        $post->increment('total_votes');
        $post->refresh();

        Log::channel('audit_trail')->info('[API] [POST] [VOTE] Vote registered.', [
            'user_id' => $user->id,
            'post_id' => $post->id,
            'option' => $request->option,
        ]);

        return $this->ok($this->voteState($post, $request->option));
    }

    /**
     * POST /posts/{post}/share
     */
    public function share(ShareRequest $request, Post $post): JsonResponse
    {
        Share::create([
            'user_id' => $request->user()->id,
            'post_id' => $post->id,
            'platform' => $request->platform,
        ]);

        $post->increment('shares_count');

        return $this->ok(['shares_count' => (int) $post->fresh()->shares_count]);
    }

    /**
     * GET /users/{username}/posts
     */
    public function userPosts(Request $request, string $username): JsonResponse
    {
        $user = User::where('username', $username)->firstOrFail();

        $posts = $user->posts()->withPostData()->latest()->paginate(10);
        $this->attachUserVotes($posts, $request);

        return $this->paginated($posts, PostResource::class);
    }

    /**
     * GET /users/{username}/voted-posts
     */
    public function userVotedPosts(Request $request, string $username): JsonResponse
    {
        $user = User::where('username', $username)->firstOrFail();
        $viewer = $request->user();

        if (! $user->show_voted_posts_publicly && (! $viewer || $viewer->id !== $user->id)) {
            return $this->error('This user has made their voted posts private.', 403, 'access_forbidden');
        }

        $posts = $user->votedPosts()->withPostData()->latest('votes.created_at')->paginate(10);
        $this->attachUserVotes($posts, $request);

        return $this->paginated($posts, PostResource::class);
    }

    // --- helpers ---

    private function attachUserVotes(LengthAwarePaginator $posts, Request $request): void
    {
        $map = collect();
        $user = $request->user();

        if ($user) {
            $ids = $posts->pluck('id')->all();
            if (! empty($ids)) {
                $map = Vote::where('user_id', $user->id)->whereIn('post_id', $ids)->pluck('vote_option', 'post_id');
            }
        }

        $posts->getCollection()->transform(function (Post $post) use ($map) {
            $post->user_vote = $map->get($post->id);

            return $post;
        });
    }

    private function userVoteFor(int $postId, Request $request): ?string
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        return Vote::where('user_id', $user->id)->where('post_id', $postId)->value('vote_option');
    }

    private function voteState(Post $post, ?string $userVote): array
    {
        return [
            'option_one_votes' => (int) $post->option_one_votes,
            'option_two_votes' => (int) $post->option_two_votes,
            'total_votes' => (int) $post->total_votes,
            'option_one_percentage' => $post->option_one_percentage,
            'option_two_percentage' => $post->option_two_percentage,
            'user_vote' => $userVote,
        ];
    }

    private function moderationError(string $field, array $result): JsonResponse
    {
        $message = $result['reason'] ?? __('messages.error_post_content_inappropriate');

        return $this->error($message, 422, 'content_rejected', [
            'errors' => [$field => [$message]],
        ]);
    }
}
