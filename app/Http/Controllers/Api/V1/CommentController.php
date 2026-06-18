<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\NewCommentPosted;
use App\Http\Requests\Api\V1\StoreCommentRequest;
use App\Http\Requests\Api\V1\UpdateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Notifications\NewReplyToYourComment;
use App\Notifications\YouWereMentioned;
use App\Services\ModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CommentController extends ApiController
{
    public function __construct(private ModerationService $moderation) {}

    /**
     * GET /posts/{post}/comments — top-level comments with a small reply preview.
     */
    public function index(Request $request, Post $post): JsonResponse
    {
        $userId = $request->user()?->id;
        $perPage = (int) $request->input('per_page', 15);

        $query = Comment::where('post_id', $post->id)
            ->whereNull('parent_id')
            ->withCount(['likes', 'replies'])
            ->with('user:id,username,first_name,last_name,profile_picture')
            ->orderBy('score', 'desc')
            ->orderBy('created_at', 'desc');

        if ($userId) {
            $query->with(['likes' => fn ($q) => $q->where('user_id', $userId)]);
        }

        $comments = $query->paginate($perPage);

        $comments->getCollection()->each(function (Comment $comment) use ($userId) {
            $this->markLiked($comment);

            $repliesQuery = $comment->flatReplies()
                ->withCount('likes')
                ->with('user:id,username,first_name,last_name,profile_picture', 'parent:id,user_id', 'parent.user:id,username')
                ->orderBy('created_at', 'asc')
                ->limit(3);

            if ($userId) {
                $repliesQuery->with(['likes' => fn ($q) => $q->where('user_id', $userId)]);
            }

            $replies = $repliesQuery->get()->each(fn (Comment $r) => $this->markLiked($r));
            $comment->setRelation('flatReplies', $replies->reverse()->values());
        });

        return $this->paginated($comments, CommentResource::class);
    }

    /**
     * GET /comments/{comment}/replies — full reply thread (paginated).
     */
    public function replies(Request $request, Comment $comment): JsonResponse
    {
        if ($comment->parent_id !== null) {
            return $this->error('Replies can only be fetched for a top-level comment.', 400, 'invalid_target');
        }

        $userId = $request->user()?->id;
        $excludeIds = (array) $request->input('exclude_ids', []);

        $query = Comment::where('root_comment_id', $comment->id)
            ->whereNotIn('id', $excludeIds)
            ->withCount('likes')
            ->with('user:id,username,first_name,last_name,profile_picture', 'parent:id,user_id', 'parent.user:id,username')
            ->orderBy('created_at', 'asc');

        if ($userId) {
            $query->with(['likes' => fn ($q) => $q->where('user_id', $userId)]);
        }

        $replies = $query->paginate(15);
        $replies->getCollection()->each(fn (Comment $r) => $this->markLiked($r));

        return $this->paginated($replies, CommentResource::class);
    }

    /**
     * POST /posts/{post}/comments
     */
    public function store(StoreCommentRequest $request, Post $post): JsonResponse
    {
        $user = $request->user();
        $content = $request->input('content');
        $lang = $user->locale ?? config('app.locale');

        $parentId = $request->input('parent_id');
        $rootId = null;
        $parentComment = null;

        if ($parentId) {
            $parentComment = Comment::find($parentId);
            if (! $parentComment || (int) $parentComment->post_id !== (int) $post->id) {
                return $this->error(__('messages.error_parent_comment_invalid'), 422, 'invalid_parent', [
                    'errors' => ['parent_id' => [__('messages.error_parent_comment_invalid')]],
                ]);
            }
            $rootId = $parentComment->root_comment_id ?? $parentComment->id;
        }

        // Moderation: URLs first, then the body text.
        foreach ($this->extractUrls($content) as $url) {
            $urlResult = $this->moderation->moderateUrl($url, $lang);
            if (! $urlResult['is_appropriate']) {
                return $this->contentRejected($urlResult['reason'] ?? __('messages.external_link_unsafe'));
            }
        }

        $textResult = $this->moderation->moderateComment($content, $lang);
        if (! $textResult['is_appropriate']) {
            $msg = $textResult['reason']
                ? __('messages.error_comment_content_inappropriate_reason', ['reason' => $textResult['reason'], 'category' => $textResult['category']])
                : __('messages.error_comment_moderation_violation');

            return $this->contentRejected($msg);
        }

        $comment = Comment::create([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'content' => $content,
            'parent_id' => $parentId,
            'root_comment_id' => $rootId,
        ]);

        $this->dispatchNotifications($comment, $user, $parentComment);

        $comment->load('user:id,username,first_name,last_name,profile_picture', 'parent.user:id,username');
        $comment->is_liked = false;

        try {
            broadcast(new NewCommentPosted($comment))->toOthers();
        } catch (\Throwable $e) {
            Log::error('API broadcast NewCommentPosted failed: '.$e->getMessage());
        }

        Log::channel('audit_trail')->info('[API] [COMMENT] [STORE] Comment created.', [
            'user_id' => $user->id,
            'comment_id' => $comment->id,
            'post_id' => $post->id,
        ]);

        return $this->created(new CommentResource($comment));
    }

    /**
     * PUT /comments/{comment}
     */
    public function update(UpdateCommentRequest $request, Comment $comment): JsonResponse
    {
        $user = $request->user();
        if ((int) $user->id !== (int) $comment->user_id) {
            return $this->error(__('messages.error_unauthorized_action'), 403, 'access_forbidden');
        }

        $content = $request->input('content');
        $lang = $user->locale ?? config('app.locale');

        foreach ($this->extractUrls($content) as $url) {
            $urlResult = $this->moderation->moderateUrl($url, $lang);
            if (! $urlResult['is_appropriate']) {
                return $this->contentRejected($urlResult['reason'] ?? __('messages.external_link_unsafe'));
            }
        }

        $textResult = $this->moderation->moderateComment($content, $lang);
        if (! $textResult['is_appropriate']) {
            $msg = $textResult['reason']
                ? __('messages.error_comment_content_inappropriate_reason', ['reason' => $textResult['reason'], 'category' => $textResult['category']])
                : __('messages.error_comment_moderation_violation');

            return $this->contentRejected($msg);
        }

        $comment->update(['content' => $content]);
        $comment->load('user:id,username,first_name,last_name,profile_picture');
        $this->markLikedByUser($comment, $user->id);

        return $this->ok(new CommentResource($comment));
    }

    /**
     * DELETE /comments/{comment}
     */
    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        $user = $request->user();
        $postOwnerId = $comment->post->user_id;

        if ((int) $user->id !== (int) $comment->user_id && (int) $user->id !== (int) $postOwnerId) {
            return $this->error(__('messages.error_unauthorized_action'), 403, 'access_forbidden');
        }

        $comment->delete();

        Log::channel('audit_trail')->info('[API] [COMMENT] [DESTROY] Comment deleted.', [
            'user_id' => $user->id,
            'comment_id' => $comment->id,
        ]);

        return $this->message(__('messages.comment_deleted_successfully'));
    }

    // --- helpers ---

    private function markLiked(Comment $comment): void
    {
        $comment->is_liked = $comment->relationLoaded('likes') && $comment->likes->isNotEmpty();
        $comment->unsetRelation('likes');
    }

    private function markLikedByUser(Comment $comment, int $userId): void
    {
        $comment->is_liked = $comment->likes()->where('user_id', $userId)->exists();
    }

    private function extractUrls(string $content): array
    {
        $urls = [];
        preg_match_all('/(\b(?:(?:https?|ftp|file):\/\/|www\.|ftp\.)[-A-Z0-9+&@#\/%=~_|$?!:,.]*[A-Z0-9+&@#\/%=~_|$])|(^|\s)www\.[-A-Z0-9+&@#\/%=~_|$?!:,.]*[A-Z0-9+&@#\/%=~_|$]/i', $content, $matches);

        if (! empty($matches[0])) {
            foreach ($matches[0] as $url) {
                $url = trim($url);
                if (stripos($url, 'www.') === 0 && stripos($url, 'http') !== 0 && stripos($url, 'ftp') !== 0) {
                    $urls[] = 'http://'.$url;
                } elseif (filter_var($url, FILTER_VALIDATE_URL)) {
                    $urls[] = $url;
                }
            }
        }

        return array_unique($urls);
    }

    private function dispatchNotifications(Comment $comment, User $actor, ?Comment $parentComment): void
    {
        if ($parentComment && (int) $parentComment->user_id !== (int) $actor->id) {
            $parentComment->user?->notify(new NewReplyToYourComment($actor, $comment));
        }

        preg_match_all('/@([\w\-]+)/', $comment->content, $matches);
        if (empty($matches[1])) {
            return;
        }

        $parentAuthorUsername = $parentComment?->user?->username;

        foreach (array_unique($matches[1]) as $username) {
            if (strtolower($username) === strtolower($actor->username)) {
                continue;
            }
            if ($parentAuthorUsername && strtolower($username) === strtolower($parentAuthorUsername)) {
                continue;
            }

            User::where('username', $username)->first()?->notify(new YouWereMentioned($actor, $comment));
        }
    }

    private function contentRejected(string $message): JsonResponse
    {
        return $this->error($message, 422, 'content_rejected', [
            'errors' => ['content' => [$message]],
        ]);
    }
}
