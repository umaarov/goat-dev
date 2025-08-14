<?php

namespace App\Http\Controllers;

use App\Events\NewCommentPosted;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Notifications\NewReplyToYourComment;
use App\Notifications\YouWereMentioned;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CommentController extends Controller
{
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

    private function checkForBannedWords(string $commentContent): ?array
    {
        $bannedWordsString = Config::get('gemini.banned_words_uz');
        if (empty($bannedWordsString)) {
            return null;
        }

        $bannedWords = array_map('trim', explode(',', strtolower($bannedWordsString)));
        $bannedWords = array_filter($bannedWords);

        if (empty($bannedWords)) {
            return null;
        }

        $lowerCommentContent = strtolower($commentContent);

        foreach ($bannedWords as $word) {
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/u', $lowerCommentContent)) {
                Log::info('Comment flagged by local blacklist.', [
                    'flagged_word' => $word,
                    'comment_snippet' => Str::limit($commentContent, 100),
                ]);
                return [
                    'is_appropriate' => false,
                    'reason' => 'Comment contains prohibited language based on local policy.',
                    'category' => 'LOCAL_POLICY_VIOLATION',
                    'error' => null
                ];
            }
        }
        return null;
    }

    private function callGeminiAPI(string $apiUrl, array $payload, string $contextForLogging, string $contentSnippet): array
    {
        try {
            $response = Http::timeout(20)->post($apiUrl, $payload); // Slightly increased timeout

            if (!$response->successful()) {
                Log::error("Gemini API request failed for {$contextForLogging}.", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'content_snippet' => $contentSnippet,
                ]);
                return ['is_appropriate' => false, 'reason' => "Content moderation service ({$contextForLogging}) unavailable due to API request failure. Status: " . $response->status(), 'category' => 'ERROR_API_REQUEST', 'error' => 'API request failed: ' . $response->status()];
            }

            $responseData = $response->json();
            $jsonString = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (!$jsonString) {
                Log::error("Gemini API response format error for {$contextForLogging} - JSON string missing.", [
                    'response' => $responseData,
                    'content_snippet' => $contentSnippet,
                ]);
                return ['is_appropriate' => false, 'reason' => "Content moderation service ({$contextForLogging}) error due to malformed API response (JSON string missing).", 'category' => 'ERROR_API_RESPONSE_FORMAT', 'error' => 'Malformed API response (JSON string missing)'];
            }

            $decodedJson = json_decode($jsonString, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error("Gemini API response JSON parsing error for {$contextForLogging}.", [
                    'json_string' => $jsonString,
                    'json_error' => json_last_error_msg(),
                    'content_snippet' => $contentSnippet,
                ]);
                return ['is_appropriate' => false, 'reason' => "Content moderation service ({$contextForLogging}) response error due to JSON parsing failure.", 'category' => 'ERROR_API_JSON_DECODE_FAIL', 'error' => 'Failed to decode JSON from moderation result: ' . json_last_error_msg()];
            }

            // CHECK SCHEMA
            $moderationData = null;
            if (isset($decodedJson['is_appropriate']) && isset($decodedJson['violation_category'])) {
                $moderationData = $decodedJson;
            } elseif (isset($decodedJson['properties']['is_appropriate']) && isset($decodedJson['properties']['violation_category'])) {
                $moderationData = $decodedJson['properties'];
            }

            if ($moderationData === null) {
                Log::error("Gemini API response structure error for {$contextForLogging} - required keys missing.", [
                    'decoded_json' => $decodedJson, // Log the entire decoded JSON for inspection
                    'json_string_source' => $jsonString,
                    'content_snippet' => $contentSnippet,
                ]);
                return ['is_appropriate' => false, 'reason' => "Content moderation service ({$contextForLogging}) response error due to invalid structure (required keys missing).", 'category' => 'ERROR_API_RESPONSE_SCHEMA', 'error' => 'Invalid structure in moderation result.'];
            }


            return [
                'is_appropriate' => (bool)$moderationData['is_appropriate'],
                'reason' => $moderationData['reason_if_inappropriate'] ?? null,
                'category' => $moderationData['violation_category'],
                'error' => null
            ];

        } catch (ConnectionException $e) {
            Log::error("Gemini API connection exception for {$contextForLogging}: " . $e->getMessage(), [
                'content_snippet' => $contentSnippet,
            ]);
            return ['is_appropriate' => false, 'reason' => "Content moderation service ({$contextForLogging}) connection error.", 'category' => 'ERROR_API_CONNECTION', 'error' => 'Connection error: ' . $e->getMessage()];
        } catch (Exception $e) {
            Log::error("Generic error during Gemini moderation for {$contextForLogging}: " . $e->getMessage(), [
                'content_snippet' => $contentSnippet,
                'trace' => Str::limit($e->getTraceAsString(), 500),
            ]);
            return ['is_appropriate' => false, 'reason' => "An unexpected error occurred during content moderation ({$contextForLogging}).", 'category' => 'ERROR_UNKNOWN', 'error' => 'Exception: ' . $e->getMessage()];
        }
    }

    private function checkCommentWithGemini(string $commentContent): array
    {
        // 1. Check local banned words first
        $localBlock = $this->checkForBannedWords($commentContent);
        if ($localBlock) {
            return $localBlock;
        }

        $apiKey = Config::get('gemini.api_key');
        if (!$apiKey) {
            Log::error('Gemini API key is not configured.');
            return ['is_appropriate' => true, 'reason' => 'Gemini API key not configured. Comment allowed without API check.', 'category' => 'UNCHECKED_CONFIG_ERROR', 'error' => 'Gemini API key not configured.'];
        }

        $model = Config::get('gemini.model', 'gemini-2.0-flash');
        $baseApiUrl = rtrim(Config::get('gemini.api_url', 'https://generativelanguage.googleapis.com/v1beta/models/'), '/');
        $apiUrl = $baseApiUrl . '/' . $model . ':generateContent?key=' . $apiKey;

        $currentLocale = App::getLocale();
        $languageNames = ['en' => 'English', 'ru' => 'Russian'];
        $languageName = $languageNames[$currentLocale] ?? 'English';

        // 2. Extract and moderate URLs
        $urls = [];
        preg_match_all('/(\b(?:(?:https?|ftp|file):\/\/|www\.|ftp\.)[-A-Z0-9+&@#\/%=~_|$?!:,.]*[A-Z0-9+&@#\/%=~_|$])|(^|\s)www\.[-A-Z0-9+&@#\/%=~_|$?!:,.]*[A-Z0-9+&@#\/%=~_|$]/i', $commentContent, $matches);

        if (!empty($matches[0])) {
            foreach ($matches[0] as $url) {
                $url = trim($url);
                if (stripos($url, 'www.') === 0 && stripos($url, 'http') !== 0 && stripos($url, 'ftp') !== 0) {
                    $urls[] = 'http://' . $url;
                } elseif (filter_var($url, FILTER_VALIDATE_URL)) {
                    $urls[] = $url;
                }
            }
            $urls = array_unique($urls);
        }

        if (!empty($urls)) {
            $urlPromptTemplate = Config::get('gemini.url_prompt_template');
            if (empty($urlPromptTemplate)) {
                Log::error('Gemini URL moderation prompt template is not configured.');
            } else {
                foreach ($urls as $urlToCheck) {
                    $finalUrlPrompt = str_replace(["{URL_TEXT}", "{LANGUAGE_NAME}"], [addslashes($urlToCheck), $languageName], $urlPromptTemplate);
                    $urlPayload = [
                        'contents' => [['parts' => [['text' => $finalUrlPrompt]]]],
                        'generationConfig' => ['responseMimeType' => 'application/json'],
                    ];
                    $urlModerationResult = $this->callGeminiAPI($apiUrl, $urlPayload, "URL: {$urlToCheck}", Str::limit($urlToCheck, 100));
                    if (!$urlModerationResult['is_appropriate']) {
                        Log::info('Comment rejected due to URL moderation.', [
                            'url' => $urlToCheck,
                            'reason' => $urlModerationResult['reason'],
                            'category' => $urlModerationResult['category'],
                            'error' => $urlModerationResult['error'],
                            'comment_snippet' => Str::limit($commentContent, 100),
                        ]);
                        return [
                            'is_appropriate' => false,
                            'reason' => 'A URL in the comment was flagged: ' . ($urlModerationResult['reason'] ?: 'Unsafe URL detected.'),
                            'category' => $urlModerationResult['category'] ?: 'URL_POLICY_VIOLATION',
                            'error' => $urlModerationResult['error']
                        ];
                    }
                }
            }
        }

        // 3. Moderate the full comment text
        $mainPromptTemplate = Config::get('gemini.prompt_template');
        if (empty($mainPromptTemplate)) {
            Log::error('Gemini main moderation prompt template is not configured.');
            return ['is_appropriate' => true, 'reason' => 'Gemini main prompt not configured. Comment allowed without full text check.', 'category' => 'UNCHECKED_CONFIG_ERROR_MAIN', 'error' => 'Main prompt not configured.'];
        }
        $intermediateMainPrompt = str_replace("{LANGUAGE_NAME}", $languageName, $mainPromptTemplate);
        $finalMainPrompt = str_replace("{COMMENT_TEXT}", addslashes($commentContent), $intermediateMainPrompt);
        $mainPayload = [
            'contents' => [['parts' => [['text' => $finalMainPrompt]]]],
            'generationConfig' => ['responseMimeType' => 'application/json'],
        ];
        $mainModerationResult = $this->callGeminiAPI($apiUrl, $mainPayload, "Main Content", Str::limit($commentContent, 100));
        if (!$mainModerationResult['is_appropriate']) {
            Log::info('Comment rejected by main content moderation.', [ /* ... */]);
        }
        return $mainModerationResult;
    }

    final public function store(Request $request, Post $post)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
            'parent_id' => 'nullable|integer|exists:comments,id',
        ]);


//        if ($validator->fails()) {
//            if ($request->expectsJson() || $request->ajax()) {
//                return response()->json(['errors' => $validator->errors()], 422);
//            }
//            return redirect()->back()->withErrors($validator)->withInput()->with('error', __('messages.error_failed_to_add_comment'));
//        }

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

//            Log::info('Checking parent comment object as seen by Laravel:', [
//                'comment_object_from_app' => $parentComment ? $parentComment->toArray() : 'NOT FOUND'
//            ]);
            // ------------------------------------------

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

//        if ($request->filled('parent_id')) {
//            $parentComment = Comment::find($request->input('parent_id'));
//            if (!$parentComment || $parentComment->post_id !== $post->id) {
//                return response()->json(['errors' => ['parent_id' => ['Invalid parent comment.']]], 422);
//            }
//        }

        $user = Auth::user();
        $content = $request->input('content');

        $moderationResult = $this->checkCommentWithGemini($content);
        $errorMessage = '';

        if (!$moderationResult['is_appropriate']) {
            $logMessage = 'Comment rejected by moderation.';
            $logContextBase = [
                'user_id' => $user->id,
                'username' => $user->username,
                'post_id' => $post->id,
                'reason_internal' => $moderationResult['reason'],
                'category_internal' => $moderationResult['category'],
                'comment_snippet' => Str::limit($content, 100),
                'ip_address' => $request->ip(),
            ];

            if ($moderationResult['category'] === 'LOCAL_POLICY_VIOLATION') {
                $logMessage = 'Comment rejected by local blacklist.';
                $errorMessage = __('messages.error_comment_moderation_violation');
            } elseif (str_starts_with($moderationResult['category'], 'ERROR_') || str_starts_with($moderationResult['category'], 'UNCHECKED_')) {
                $errorMessage = __('messages.error_comment_moderation_system_issue');
                Log::warning('Moderation Service Error/Unavailable. Comment rejected/pending by policy.', array_merge($logContextBase, ['service_error_details' => $moderationResult['error']]));
            } else {
                $logMessage = 'Comment rejected by Gemini AI moderation.';
                $categoryDisplay = Str::ucfirst(strtolower(str_replace('_', ' ', $moderationResult['category'])));
                if ($moderationResult['reason']) {
                    $errorMessage = __('messages.error_comment_content_inappropriate_reason', [
                        'category' => $categoryDisplay,
                        'reason' => $moderationResult['reason']
                    ]);
                } elseif ($moderationResult['category'] && $moderationResult['category'] !== 'NONE') {
                    $errorMessage = __('messages.error_comment_content_inappropriate_category', [
                        'category' => $categoryDisplay
                    ]);
                } else {
                    $errorMessage = __('messages.error_comment_moderation_violation');
                }
            }
            Log::channel('audit_trail')->info($logMessage, $logContextBase);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['errors' => ['content' => [$errorMessage]]], 422);
            }
            return redirect()->back()->withInput()->with('error', $errorMessage);
        }

        $comment = Comment::create([
            'user_id' => Auth::id(),
            'post_id' => $post->id,
            'content' => $content,
            'parent_id' => $request->input('parent_id', null),
            'root_comment_id' => $rootId,
        ]);

        $this->dispatchNotifications($comment, $user, $parentComment);

        Log::channel('audit_trail')->info('Comment created and passed moderation.', [
            'user_id' => $user->id,
            'username' => $user->username,
            'comment_id' => $comment->id,
            'post_id' => $post->id,
            'moderation_category_by_gemini' => $moderationResult['category'] ?? 'N/A',
            'comment_snippet' => Str::limit($comment->content, 100),
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
                if (strtolower($username) === strtolower($actor->username)) {
                    continue;
                }

                if ($parentComment && strtolower($username) === strtolower($parentAuthorUsername)) {
                    continue;
                }

                $mentionedUser = User::where('username', $username)->first();
                if ($mentionedUser) {
                    $mentionedUser->notify(new YouWereMentioned($actor, $comment));
                }
            }
        }
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

    final public function update(Request $request, Comment $comment): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        if (Auth::id() !== $comment->user_id) {
            Log::channel('audit_trail')->warning('Unauthorized comment update attempt.', [
                'attempting_user_id' => Auth::id(),
                'comment_id' => $comment->id,
                'original_commenter_id' => $comment->user_id,
                'ip_address' => $request->ip(),
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
        $moderationResult = $this->checkCommentWithGemini($newContent);
        $errorMessage = '';

        if (!$moderationResult['is_appropriate']) {
            $logMessage = 'Comment update rejected by moderation.';
            $logContextBase = [
                'user_id' => $user->id,
                'username' => $user->username,
                'comment_id' => $comment->id,
                'reason_internal' => $moderationResult['reason'],
                'category_internal' => $moderationResult['category'],
                'updated_content_snippet' => Str::limit($newContent, 100),
                'ip_address' => $request->ip(),
            ];

            if ($moderationResult['category'] === 'LOCAL_POLICY_VIOLATION') {
                $logMessage = 'Comment update rejected by local blacklist.';
                $errorMessage = __('messages.error_comment_update_moderation_violation');
            } elseif (str_starts_with($moderationResult['category'], 'ERROR_') || str_starts_with($moderationResult['category'], 'UNCHECKED_')) {
                $errorMessage = __('messages.error_comment_update_moderation_system_issue');
                Log::warning('Moderation Service Error/Unavailable during comment update.', array_merge($logContextBase, ['service_error_details' => $moderationResult['error']]));
            } else {
                $logMessage = 'Comment update rejected by Gemini AI moderation.';
                $categoryDisplay = Str::ucfirst(strtolower(str_replace('_', ' ', $moderationResult['category'])));
                if ($moderationResult['reason']) {
                    $errorMessage = __('messages.error_comment_update_content_inappropriate_reason', [
                        'category' => $categoryDisplay,
                        'reason' => $moderationResult['reason']
                    ]);
                } elseif ($moderationResult['category'] && $moderationResult['category'] !== 'NONE') {
                    $errorMessage = __('messages.error_comment_update_content_inappropriate_category', [
                        'category' => $categoryDisplay
                    ]);
                } else {
                    $errorMessage = __('messages.error_comment_update_moderation_violation');
                }
            }
            Log::channel('audit_trail')->info($logMessage, $logContextBase);

            if ($request->expectsJson()) {
                return response()->json(['errors' => ['content' => [$errorMessage]]], 422);
            }
            return redirect()->back()->withInput()->with('error', $errorMessage);
        }

        $comment->update(['content' => $newContent]);
        Log::channel('audit_trail')->info('Comment updated and passed moderation.', [
            'user_id' => $user->id,
            'username' => $user->username,
            'comment_id' => $comment->id,
            'moderation_category_by_gemini' => $moderationResult['category'] ?? 'N/A',
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
            Log::channel('audit_trail')->warning('Unauthorized comment deletion attempt.', [
                'attempting_user_id' => Auth::id(),
                'comment_id' => $comment->id,
                'original_commenter_id' => $comment->user_id,
                'post_owner_id' => $postOwnerId,
                'ip_address' => $request->ip(),
            ]);
            $unauthorizedMessage = __('messages.error_unauthorized_action');
            if ($request->expectsJson()) return response()->json(['error' => $unauthorizedMessage], 403);
            abort(403, $unauthorizedMessage);
        }
        $commentId = $comment->id;
        $originalCommenterId = $comment->user_id;
        $commentSnippet = Str::limit($comment->content, 100);
        $postId = $comment->post_id;

        $comment->delete();

        Log::channel('audit_trail')->info('Comment deleted.', [
            'deleter_user_id' => $user->id,
            'deleter_username' => $user->username,
            'deleted_comment_id' => $commentId,
            'original_commenter_id' => $originalCommenterId,
            'original_comment_snippet' => $commentSnippet,
            'post_id' => $postId,
            'ip_address' => $request->ip(),
        ]);

        $successMessage = __('messages.comment_deleted_successfully');
        if ($request->expectsJson()) {
            return response()->json(['message' => $successMessage]);
        }

        return redirect()->back()->with('success', $successMessage);
    }
}
