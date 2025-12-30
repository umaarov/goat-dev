<?php

namespace App\Http\Controllers;

use App\Events\NewCommentPosted;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Notifications\NewReplyToYourComment;
use App\Notifications\YouWereMentioned;
use App\Services\ModerationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CommentController extends Controller
{
    protected ModerationService $moderationService;

    public function __construct(ModerationService $moderationService)
    {
        $this->moderationService = $moderationService;
    }

    public function index(Request $request, Post $post): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $userId = Auth::id();

        $commentsQuery = Comment::where('post_id', $post->id)
            ->whereNull('parent_id')
            ->withCount(['likes', 'replies'])
            ->with('user:id,username,profile_picture')
            ->orderBy('score', 'desc')
            ->orderBy('created_at', 'desc');

        if ($userId) {
            $commentsQuery->with(['likes' => fn($q) => $q->where('user_id', $userId)]);
        }

        $comments = $commentsQuery->paginate($perPage);

        $comments->getCollection()->each(function ($comment) use ($userId) {
            $comment->is_liked_by_current_user = $comment->likes->isNotEmpty();
            unset($comment->likes);

            $initialRepliesQuery = $comment->flatReplies()
                ->withCount('likes')
                ->with('user:id,username,profile_picture', 'parent:id,user_id', 'parent.user:id,username')
                ->orderBy('created_at', 'asc')
                ->limit(3);

            if ($userId) {
                $initialRepliesQuery->with(['likes' => fn($q) => $q->where('user_id', $userId)]);
            }

            $initialReplies = $initialRepliesQuery->get();

            $processedReplies = $initialReplies->each(function ($reply) {
                $reply->is_liked_by_current_user = $reply->likes->isNotEmpty();
                unset($reply->likes);
            });

            $comment->setRelation('flatReplies', $processedReplies->reverse()->values());
        });

        return response()->json(['comments' => $comments]);
    }

    public function getReplies(Request $request, Comment $comment): JsonResponse
    {
        if ($comment->parent_id !== null) {
            return response()->json(['error' => 'Can only fetch replies for a top-level comment.'], 400);
        }

        $userId = Auth::id();
        $perPage = 15;
        $excludeIds = $request->input('exclude_ids', []);

        $repliesQuery = Comment::where('root_comment_id', $comment->id)
            ->whereNotIn('id', $excludeIds)
            ->withCount('likes')
            ->with([
                'user:id,username,profile_picture',
                'parent:id,user_id',
                'parent.user:id,username'
            ])
            ->orderBy('created_at', 'asc');

        if ($userId) {
            $repliesQuery->with(['likes' => fn($q) => $q->where('user_id', $userId)]);
        }

        $replies = $repliesQuery->paginate($perPage);

        $replies->getCollection()->each(function ($reply) {
            $reply->is_liked_by_current_user = $reply->likes->isNotEmpty();
            unset($reply->likes);
        });

        return response()->json($replies);
    }

    final public function store(Request $request, Post $post)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
            'parent_id' => 'nullable|integer|exists:comments,id',
        ]);

        if ($validator->fails()) {
            if ($validator->errors()->has('parent_id')) {
                return response()->json([
                    'errors' => ['parent_id' => [__('messages.error_parent_comment_deleted', ['default' => 'The comment you are replying to has been deleted.'])]]
                ], 422);
            }
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $parentId = $request->input('parent_id');
        $rootId = null;
        $parentComment = null;

        if ($parentId) {
            $parentComment = Comment::find($parentId);

            if (!$parentComment || (int)$parentComment->post_id !== (int)$post->id) {
                Log::warning('Parent comment mismatch attempt.', [
                    'user_id' => Auth::id(),
                    'post_id' => $post->id,
                    'parent_id_submitted' => $parentId,
                ]);
                return response()->json(['errors' => ['parent_id' => [__('messages.error_parent_comment_invalid')]]], 422);
            }
            $rootId = $parentComment->root_comment_id ?? $parentComment->id;
        }

        $user = Auth::user();
        $content = $request->input('content');
        $locale = App::getLocale();

        // --- START PROFESSIONAL MODERATION CHECK (GROQ) ---

        // 1. Extract and Moderate URLs (Stricter Policy)
        $urls = $this->extractUrls($content);
        foreach ($urls as $url) {
            $urlResult = $this->moderationService->moderateUrl($url, $locale);

            if (!$urlResult['is_appropriate']) {
                $this->logModerationFailure($user, $post->id, $url, $urlResult, 'URL_VIOLATION');

                $msg = $urlResult['reason'] ?? __('messages.external_link_unsafe');
                return $this->sendModerationErrorResponse($request, $msg);
            }
        }

        // 2. Moderate Main Text
        $textResult = $this->moderationService->moderateComment($content, $locale);

        if (!$textResult['is_appropriate']) {
            $this->logModerationFailure($user, $post->id, $content, $textResult, 'TEXT_VIOLATION');

            $msg = $textResult['reason']
                ? __('messages.error_comment_content_inappropriate_reason', ['reason' => $textResult['reason'], 'category' => $textResult['category']])
                : __('messages.error_comment_moderation_violation');

            return $this->sendModerationErrorResponse($request, $msg);
        }

        // --- END MODERATION CHECK ---

        $comment = Comment::create([
            'user_id' => Auth::id(),
            'post_id' => $post->id,
            'content' => $content,
            'parent_id' => $request->input('parent_id', null),
            'root_comment_id' => $rootId,
        ]);

        $this->dispatchNotifications($comment, $user, $parentComment);

        Log::channel('audit_trail')->info('[COMMENT] [STORE] Comment created and passed Groq moderation.', [
            'user_id' => $user->id,
            'comment_id' => $comment->id,
            'post_id' => $post->id,
            'ip_address' => $request->ip(),
        ]);

        $comment->load('user:id,username,profile_picture', 'post:id,user_id', 'parent.user');

        try {
            broadcast(new NewCommentPosted($comment))->toOthers();
        } catch (Exception $e) {
            Log::error('Broadcasting NewCommentPosted failed: ' . $e->getMessage());
        }

        $successMessage = __('messages.comment_added_successfully');
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['message' => $successMessage, 'comment' => $comment], 201);
        }
        return redirect()->back()->with('success', $successMessage);
    }

    final public function update(Request $request, Comment $comment): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        if (Auth::id() !== $comment->user_id) {
            Log::channel('audit_trail')->warning('[COMMENT] [UPDATE] Unauthorized comment update attempt.', [
                'user_id' => Auth::id(),
                'comment_id' => $comment->id,
            ]);
            $unauthorizedMessage = __('messages.error_unauthorized_action');
            if ($request->expectsJson()) return response()->json(['error' => $unauthorizedMessage], 403);
            abort(403, $unauthorizedMessage);
        }

        $validator = Validator::make($request->all(), ['content' => 'required|string|max:1000']);
        if ($validator->fails()) {
            if ($request->expectsJson()) return response()->json(['errors' => $validator->errors()], 422);
            return redirect()->back()->withErrors($validator)->withInput()->with('error', __('messages.error_failed_to_update_comment'));
        }

        $newContent = $request->input('content');
        $locale = App::getLocale();

        // --- START PROFESSIONAL MODERATION CHECK ---

        // 1. Moderate URLs
        $urls = $this->extractUrls($newContent);
        foreach ($urls as $url) {
            $urlResult = $this->moderationService->moderateUrl($url, $locale);
            if (!$urlResult['is_appropriate']) {
                $this->logModerationFailure($user, $comment->post_id, $url, $urlResult, 'UPDATE_URL_VIOLATION');
                $msg = $urlResult['reason'] ?? __('messages.external_link_unsafe');
                return $this->sendModerationErrorResponse($request, $msg);
            }
        }

        // 2. Moderate Text
        $textResult = $this->moderationService->moderateComment($newContent, $locale);
        if (!$textResult['is_appropriate']) {
            $this->logModerationFailure($user, $comment->post_id, $newContent, $textResult, 'UPDATE_TEXT_VIOLATION');

            $msg = $textResult['reason']
                ? __('messages.error_comment_content_inappropriate_reason', ['reason' => $textResult['reason'], 'category' => $textResult['category']])
                : __('messages.error_comment_moderation_violation');

            return $this->sendModerationErrorResponse($request, $msg);
        }

        // --- END MODERATION CHECK ---

        $comment->update(['content' => $newContent]);

        Log::channel('audit_trail')->info('[COMMENT] [MODERATION] Comment updated and passed Groq moderation.', [
            'user_id' => $user->id,
            'comment_id' => $comment->id,
            'ip_address' => $request->ip(),
        ]);

        $successMessage = __('messages.comment_updated_successfully');
        if ($request->expectsJson()) {
            $comment->load('user:id,username,profile_picture');
            return response()->json(['message' => $successMessage, 'comment' => $comment]);
        }
        return redirect()->back()->with('success', $successMessage);
    }

    final public function destroy(Request $request, Comment $comment): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        $postOwnerId = $comment->post->user_id;

        if ((int)Auth::id() !== (int)$comment->user_id && (int)Auth::id() !== (int)$postOwnerId) {
            Log::channel('audit_trail')->warning('[COMMENT] [DESTROY] Unauthorized comment deletion attempt.', [
                'user_id' => Auth::id(),
                'comment_id' => $comment->id,
            ]);
            $unauthorizedMessage = __('messages.error_unauthorized_action');
            if ($request->expectsJson()) return response()->json(['error' => $unauthorizedMessage], 403);
            abort(403, $unauthorizedMessage);
        }

        $comment->delete();

        Log::channel('audit_trail')->info('[COMMENT] [DESTROY] Comment deleted.', [
            'user_id' => $user->id,
            'comment_id' => $comment->id,
            'ip_address' => $request->ip(),
        ]);

        $successMessage = __('messages.comment_deleted_successfully');
        if ($request->expectsJson()) {
            return response()->json(['message' => $successMessage]);
        }

        return redirect()->back()->with('success', $successMessage);
    }

    public function showCommentContext(Request $request, Post $post, Comment $comment): JsonResponse
    {
        if ((int)$comment->post_id !== (int)$post->id) {
            abort(404, 'Comment does not belong to this post.');
        }

        $rootComment = $comment->root_comment_id ? Comment::find($comment->root_comment_id) : $comment;
        if (!$rootComment) {
            abort(404, 'Root comment not found.');
        }

        $perPage = 10;
        $newerOrSameCommentsCount = Comment::where('post_id', $post->id)
            ->whereNull('parent_id')
            ->where(function ($query) use ($rootComment) {
                $query->where('created_at', '>', $rootComment->created_at)
                    ->orWhere(function ($subQuery) use ($rootComment) {
                        $subQuery->where('created_at', $rootComment->created_at)
                            ->where('id', '>=', $rootComment->id);
                    });
            })
            ->count();

        $page = (int)ceil($newerOrSameCommentsCount / $perPage);
        if ($page < 1) $page = 1;

        $request->merge(['page' => $page]);
        return $this->index($request, $post);
    }

    // --- PRIVATE HELPER METHODS ---

    private function dispatchNotifications(Comment $comment, User $actor, ?Comment $parentComment): void
    {
        if ($parentComment && (int)$parentComment->user_id !== (int)$actor->id) {
            $parentComment->user->notify(new NewReplyToYourComment($actor, $comment));
        }

        preg_match_all('/@([\w\-]+)/', $comment->content, $matches);
        if (!empty($matches[1])) {
            $mentionedUsernames = array_unique($matches[1]);
            $parentAuthorUsername = $parentComment ? $parentComment->user->username : null;

            foreach ($mentionedUsernames as $username) {
                if (strtolower($username) === strtolower($actor->username)) continue;
                if ($parentComment && strtolower($username) === strtolower($parentAuthorUsername)) continue;

                $mentionedUser = User::where('username', $username)->first();
                if ($mentionedUser) {
                    $mentionedUser->notify(new YouWereMentioned($actor, $comment));
                }
            }
        }
    }

    /**
     * Extracts URLs from content for individual moderation.
     */
    private function extractUrls(string $content): array
    {
        $urls = [];
        preg_match_all('/(\b(?:(?:https?|ftp|file):\/\/|www\.|ftp\.)[-A-Z0-9+&@#\/%=~_|$?!:,.]*[A-Z0-9+&@#\/%=~_|$])|(^|\s)www\.[-A-Z0-9+&@#\/%=~_|$?!:,.]*[A-Z0-9+&@#\/%=~_|$]/i', $content, $matches);

        if (!empty($matches[0])) {
            foreach ($matches[0] as $url) {
                $url = trim($url);
                if (stripos($url, 'www.') === 0 && stripos($url, 'http') !== 0 && stripos($url, 'ftp') !== 0) {
                    $urls[] = 'http://' . $url;
                } elseif (filter_var($url, FILTER_VALIDATE_URL)) {
                    $urls[] = $url;
                }
            }
        }
        return array_unique($urls);
    }

    private function logModerationFailure(User $user, int $postId, string $snippet, array $result, string $type): void
    {
        Log::channel('audit_trail')->info("[COMMENT] [MODERATION] Comment rejected by Groq ({$type}).", [
            'user_id' => $user->id,
            'username' => $user->username,
            'post_id' => $postId,
            'reason' => $result['reason'] ?? 'Unknown',
            'category' => $result['category'] ?? 'UNKNOWN',
            'snippet' => Str::limit($snippet, 100),
            'ip_address' => request()->ip(),
        ]);
    }

    private function sendModerationErrorResponse(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['errors' => ['content' => [$message]]], 422);
        }
        return redirect()->back()->withInput()->with('error', $message);
    }
}
