<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Post;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CommentController extends Controller
{
    public function index(Request $request, Post $post)
    {
        $perPage = $request->input('per_page', 10);
        $comments = Comment::where('post_id', $post->id)
            ->with('user:id,username,profile_picture')
            ->with('post:id,user_id')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'comments' => $comments
        ]);
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

    private function checkCommentWithGemini(string $commentContent): array
    {
        $localBlock = $this->checkForBannedWords($commentContent);
        if ($localBlock) {
            return $localBlock;
        }

        $apiKey = Config::get('gemini.api_key');
        if (!$apiKey) {
            Log::error('Gemini API key is not configured.');
            return ['is_appropriate' => true, 'reason' => null, 'category' => 'UNCHECKED_CONFIG_ERROR', 'error' => 'Gemini API key not configured. Comment allowed without check.'];
        }

        $model = Config::get('gemini.model', 'gemini-2.0-flash');
        $apiUrl = rtrim(Config::get('gemini.api_url', 'https://generativelanguage.googleapis.com/v1beta/models/'), '/') . '/' . $model . ':generateContent?key=' . $apiKey;
        $promptTemplate = Config::get('gemini.prompt_template');

        if (empty($promptTemplate)) {
            Log::error('Gemini moderation prompt template is not configured.');
            return ['is_appropriate' => true, 'reason' => null, 'category' => 'UNCHECKED_CONFIG_ERROR', 'error' => 'Gemini prompt not configured. Comment allowed without check.'];
        }

        $finalPrompt = str_replace("{COMMENT_TEXT}", addslashes($commentContent), $promptTemplate);

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $finalPrompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
            ],
        ];

        try {
            $response = Http::timeout(15)->post($apiUrl, $payload);

            if (!$response->successful()) {
                Log::error('Gemini API request failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'comment_snippet' => Str::limit($commentContent, 100),
                ]);
                return ['is_appropriate' => false, 'reason' => 'Content moderation service unavailable.', 'category' => 'ERROR_API_REQUEST', 'error' => 'API request failed: ' . $response->status()];
            }

            $responseData = $response->json();
            $jsonString = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (!$jsonString) {
                Log::error('Gemini API response format error - JSON string missing.', [
                    'response' => $responseData,
                    'comment_snippet' => Str::limit($commentContent, 100),
                ]);
                return ['is_appropriate' => false, 'reason' => 'Content moderation service error.', 'category' => 'ERROR_API_RESPONSE_FORMAT', 'error' => 'Malformed API response (JSON string missing)'];
            }

            $moderationResult = json_decode($jsonString, true);

            if (json_last_error() !== JSON_ERROR_NONE || !isset($moderationResult['is_appropriate']) || !isset($moderationResult['violation_category'])) {
                Log::error('Gemini API response parsing error or invalid structure.', [
                    'json_string' => $jsonString,
                    'json_error' => json_last_error_msg(),
                    'comment_snippet' => Str::limit($commentContent, 100),
                ]);
                return ['is_appropriate' => false, 'reason' => 'Content moderation service response error.', 'category' => 'ERROR_API_RESPONSE_PARSE', 'error' => 'Failed to parse or validate moderation result.'];
            }

            return [
                'is_appropriate' => (bool)$moderationResult['is_appropriate'],
                'reason' => $moderationResult['reason_if_inappropriate'] ?? null,
                'category' => $moderationResult['violation_category'],
                'error' => null
            ];

        } catch (ConnectionException $e) {
            Log::error('Gemini API connection exception: ' . $e->getMessage(), [
                'comment_snippet' => Str::limit($commentContent, 100),
            ]);
            return ['is_appropriate' => false, 'reason' => 'Content moderation service connection error.', 'category' => 'ERROR_API_CONNECTION', 'error' => 'Connection error: ' . $e->getMessage()];
        } catch (Exception $e) {
            Log::error('Generic error during Gemini moderation: ' . $e->getMessage(), [
                'comment_snippet' => Str::limit($commentContent, 100),
                'trace' => Str::limit($e->getTraceAsString(), 500),
            ]);
            return ['is_appropriate' => false, 'reason' => 'An unexpected error occurred during content moderation.', 'category' => 'ERROR_UNKNOWN', 'error' => 'Exception: ' . $e->getMessage()];
        }
    }

    final public function store(Request $request, Post $post)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput()->with('error', 'Failed to add comment. Please check the errors.');
        }

        $user = Auth::user();
        $content = $request->input('content');

        $moderationResult = $this->checkCommentWithGemini($content);

        if (!$moderationResult['is_appropriate']) {
            $errorMessage = 'Your comment could not be posted as it violates community guidelines.';
            $logMessage = 'Comment rejected by moderation.';
            $logContextBase = [
                'user_id' => $user->id,
                'username' => $user->username,
                'post_id' => $post->id,
                'reason' => $moderationResult['reason'],
                'category' => $moderationResult['category'],
                'comment_snippet' => Str::limit($content, 100),
                'ip_address' => $request->ip(),
            ];

            if ($moderationResult['category'] === 'LOCAL_POLICY_VIOLATION') {
                $logMessage = 'Comment rejected by local blacklist.';
            } elseif (str_starts_with($moderationResult['category'], 'ERROR_') || str_starts_with($moderationResult['category'], 'UNCHECKED_')) {
                $errorMessage = 'Could not verify comment content due to a system issue. Please try again later.';
                Log::warning('Moderation Service Error/Unavailable. Comment rejected/pending by policy.', array_merge($logContextBase, ['service_error_details' => $moderationResult['error']]));
            } else {
                $logMessage = 'Comment rejected by Gemini AI moderation.';
                if ($moderationResult['reason']) {
                    $errorMessage .= ' Reason: ' . Str::ucfirst(strtolower(str_replace('_', ' ', $moderationResult['category']))) . ' - ' . $moderationResult['reason'];
                } elseif ($moderationResult['category'] !== 'NONE') {
                    $errorMessage .= ' Category: ' . Str::ucfirst(strtolower(str_replace('_', ' ', $moderationResult['category'])));
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
        ]);

        Log::channel('audit_trail')->info('Comment created and passed moderation.', [
            'user_id' => $user->id,
            'username' => $user->username,
            'comment_id' => $comment->id,
            'post_id' => $post->id,
            'moderation_category_by_gemini' => $moderationResult['category'] ?? 'N/A',
            'comment_snippet' => Str::limit($comment->content, 100),
            'ip_address' => $request->ip(),
        ]);

        $comment->load('user:id,username,profile_picture');
        $comment->load('post:id,user_id');

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['message' => 'Comment added successfully!', 'comment' => $comment], 201);
        }
        return redirect()->back()->with('success', 'Comment added successfully!');
    }

    final public function update(Request $request, Comment $comment): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        if (Auth::id() !== $comment->user_id) {
            Log::channel('audit_trail')->warning('Unauthorized comment update attempt.', [ /* ... */]);
            if ($request->expectsJson()) return response()->json(['error' => 'Unauthorized'], 403);
            abort(403, 'Unauthorized action.');
        }

        $validator = Validator::make($request->all(), ['content' => 'required|string|max:1000']);
        if ($validator->fails()) {
            if ($request->expectsJson()) return response()->json(['errors' => $validator->errors()], 422);
            return redirect()->back()->withErrors($validator)->withInput()->with('error', 'Failed to update comment.');
        }

        $newContent = $request->input('content');
        $moderationResult = $this->checkCommentWithGemini($newContent);

        if (!$moderationResult['is_appropriate']) {
            $errorMessage = 'Your updated comment could not be saved as it violates community guidelines.';
            $logMessage = 'Comment update rejected by moderation.';
            $logContextBase = [
                'user_id' => $user->id,
                'username' => $user->username,
                'comment_id' => $comment->id,
                'reason' => $moderationResult['reason'],
                'category' => $moderationResult['category'],
                'updated_content_snippet' => Str::limit($newContent, 100),
                'ip_address' => $request->ip(),
            ];

            if ($moderationResult['category'] === 'LOCAL_POLICY_VIOLATION') {
                $logMessage = 'Comment update rejected by local blacklist.';
            } elseif (str_starts_with($moderationResult['category'], 'ERROR_') || str_starts_with($moderationResult['category'], 'UNCHECKED_')) {
                $errorMessage = 'Could not verify updated comment content due to a system issue. Please try again later.';
                Log::warning('Moderation Service Error/Unavailable during comment update.', array_merge($logContextBase, ['service_error_details' => $moderationResult['error']]));
            } else { // Gemini found it inappropriate
                $logMessage = 'Comment update rejected by Gemini AI moderation.';
                if ($moderationResult['reason']) {
                    $errorMessage .= ' Reason: ' . Str::ucfirst(strtolower(str_replace('_', ' ', $moderationResult['category']))) . ' - ' . $moderationResult['reason'];
                } elseif ($moderationResult['category'] !== 'NONE') {
                    $errorMessage .= ' Category: ' . Str::ucfirst(strtolower(str_replace('_', ' ', $moderationResult['category'])));
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

        if ($request->expectsJson()) {
            $comment->load('user:id,username,profile_picture');
            return response()->json(['message' => 'Comment updated successfully!', 'comment' => $comment]);
        }
        return redirect()->back()->with('success', 'Comment updated successfully!');
    }

    final public function destroy(Request $request, Comment $comment): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        $postOwnerId = $comment->post->user_id;
        if (Auth::id() !== $comment->user_id && Auth::id() !== $postOwnerId) {
            Log::channel('audit_trail')->warning('Unauthorized comment deletion attempt.', [ /* ... */]);
            if ($request->expectsJson()) return response()->json(['error' => 'Unauthorized'], 403);
            abort(403, 'Unauthorized action.');
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

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Comment deleted successfully!']);
        }

        return redirect()->back()->with('success', 'Comment deleted successfully.');
    }
}
