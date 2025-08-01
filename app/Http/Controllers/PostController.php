<?php

namespace App\Http\Controllers;

use App\Jobs\PingSearchEngines;
use App\Jobs\SharePostToSocialMedia;
use App\Models\Post;
use App\Models\User;
use App\Models\Vote;
use App\Services\GoogleIndexingService;
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
use Illuminate\Support\Facades\Http;
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

    public function __construct(LevenshteinService $levenshteinService)
    {
        $this->levenshteinService = $levenshteinService;
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
            'option_one_image.max' => __('messages.validation_option_one_image_max', ['maxMB' => self::MAX_POST_IMAGE_SIZE_MB]),
            'option_two_image.max' => __('messages.validation_option_two_image_max', ['maxMB' => self::MAX_POST_IMAGE_SIZE_MB]),
            'option_one_image.uploaded' => __('messages.validation_option_one_image_uploaded', ['maxMB' => self::MAX_POST_IMAGE_SIZE_MB]),
            'option_two_image.uploaded' => __('messages.validation_option_two_image_uploaded', ['maxMB' => self::MAX_POST_IMAGE_SIZE_MB]),
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $user = Auth::user();
        $moderationErrorField = null;
        $defaultModerationErrorMessage = __('messages.error_post_moderation_violation');
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
                $moderationErrorMessage = __($bannedWordCheck['translation_key'], $bannedWordCheck['translation_params']);
                Log::channel('audit_trail')->info('Post creation rejected by local blacklist.', array_merge($logContextBase, ['field' => $field, 'reason_key' => $bannedWordCheck['translation_key'], 'category' => $bannedWordCheck['category'], 'content_snippet' => Str::limit($content, 50)]));
                return redirect()->back()->withErrors([$moderationErrorField => $moderationErrorMessage])->withInput();
            }

            // 2. Gemini Text Check
            if (!App::isLocal()) {
                $geminiTextCheck = $this->moderateTextWithGemini($content, $field);
                if (!$geminiTextCheck['is_appropriate']) {
                    $moderationErrorField = $field;
                    $categoryKey = 'messages.gemini_category_' . $geminiTextCheck['category'];
                    $translatedCategoryDisplay = trans()->has($categoryKey) ? __($categoryKey) : Str::ucfirst(strtolower(str_replace('_', ' ', $geminiTextCheck['category'])));
                    $reasonText = $geminiTextCheck['reason'] ?? $translatedCategoryDisplay;

                    if (str_starts_with($geminiTextCheck['category'], 'ERROR_') || str_starts_with($geminiTextCheck['category'], 'UNCHECKED_')) {
                        $moderationErrorMessage = __('messages.error_post_moderation_system_issue', ['field' => __("messages.field_name_$field", [], App::getLocale())]);
                        Log::warning('Gemini Text Moderation Service Error during post creation.', array_merge($logContextBase, ['field' => $field, 'details' => $geminiTextCheck]));
                    } else {
                        $actualReasonForMessage = $geminiTextCheck['reason'] ? $geminiTextCheck['reason'] : $translatedCategoryDisplay;
                        $moderationErrorMessage = __('messages.error_post_content_inappropriate', [
                            'field' => __("messages.field_name_$field", [], App::getLocale()),
                            'reason' => $actualReasonForMessage
                        ]);
                        Log::channel('audit_trail')->info('Post creation rejected by Gemini text moderation.', array_merge($logContextBase, ['field' => $field, 'reason' => $geminiTextCheck['reason'], 'category' => $geminiTextCheck['category'], 'content_snippet' => Str::limit($content, 50)]));
                    }
                    return redirect()->back()->withErrors([$moderationErrorField => $moderationErrorMessage])->withInput();
                }
            }
        }

        // 3. Gemini Image Checks
        $imagesToModerate = [];
        if ($request->hasFile('option_one_image')) $imagesToModerate['option_one_image'] = $request->file('option_one_image');
        if ($request->hasFile('option_two_image')) $imagesToModerate['option_two_image'] = $request->file('option_two_image');

        $postContext = [
            'question' => $request->input('question'),
            'option_one_title' => $request->input('option_one_title'),
            'option_two_title' => $request->input('option_two_title'),
        ];

        foreach ($imagesToModerate as $field => $imageFile) {
            // Moderate images only in non-local environments
            if (!App::isLocal()) {
                $geminiImageCheck = $this->moderateImageWithGemini($imageFile, $field, $postContext);
                if (!$geminiImageCheck['is_appropriate']) {
                    $moderationErrorField = $field;
                    $reasonText = $geminiImageCheck['reason'] ?? $geminiImageCheck['category'];
                    if (str_starts_with($geminiImageCheck['category'], 'ERROR_') || str_starts_with($geminiImageCheck['category'], 'UNCHECKED_')) {
                        $moderationErrorMessage = __('messages.error_post_image_moderation_system_issue', ['field' => $field]);
                        Log::warning('Gemini Image Moderation Service Error during post creation.', array_merge($logContextBase, ['field' => $field, 'details' => $geminiImageCheck]));
                    } else {
                        $moderationErrorMessage = __('messages.error_post_image_inappropriate', ['field' => $field, 'reason' => $reasonText]);
                        Log::channel('audit_trail')->info('Post creation rejected by Gemini image moderation.', array_merge($logContextBase, ['field' => $field, 'reason' => $reasonText, 'category' => $geminiImageCheck['category']]));
                    }
                    return redirect()->back()->withErrors([$moderationErrorField => $moderationErrorMessage])->withInput();
                }
            }
        }
        // --- End Moderation Stage ---

        // Process and store images
        $optionOneImagePaths = null;
        if ($request->hasFile('option_one_image')) {
            $optionOneImagePaths = $this->processAndStoreImage($request->file('option_one_image'), 'post_images', uniqid('post_opt1_'));
        }

        $optionTwoImagePaths = null;
        if ($request->hasFile('option_two_image')) {
            $optionTwoImagePaths = $this->processAndStoreImage($request->file('option_two_image'), 'post_images', uniqid('post_opt2_'));
        }

        if (!$optionOneImagePaths || !$optionTwoImagePaths) {
            Log::error('Failed to process required images during post creation', [
                'user_id' => $user->id,
                'option_one_processed' => !is_null($optionOneImagePaths),
                'option_two_processed' => !is_null($optionTwoImagePaths)
            ]);
            return redirect()->back()->withErrors(['general' => 'Failed to process uploaded images. Please try again.'])->withInput();
        }

        $post = Post::create([
            'user_id' => Auth::id(),
            'question' => $request->question,
            'slug' => Str::slug($request->question),
            'option_one_title' => $request->option_one_title,
            'option_one_image' => $optionOneImagePaths['main'],
            'option_one_image_lqip' => $optionOneImagePaths['lqip'],
            'option_two_title' => $request->option_two_title,
            'option_two_image' => $optionTwoImagePaths['main'],
            'option_two_image_lqip' => $optionTwoImagePaths['lqip'],
        ]);

        Log::info('Post created, preparing for AI generation and social sharing', [
            'post_id' => $post->id,
            'user_id' => $user->id,
            'option_one_image' => $post->option_one_image,
            'option_two_image' => $post->option_two_image,
            'images_exist_in_storage' => [
                'option_one' => Storage::disk('public')->exists($post->option_one_image),
                'option_two' => Storage::disk('public')->exists($post->option_two_image)
            ]
        ]);

        if (app()->environment('production')) {
            $post->ai_generated_context = $this->generateContextWithGemini(
                $post->question,
                $post->option_one_title,
                $post->option_two_title
            );

            $post->ai_generated_tags = $this->generateTagsWithGemini(
                $post->question,
                $post->option_one_title,
                $post->option_two_title
            );

            if ($post->isDirty('ai_generated_context') || $post->isDirty('ai_generated_tags')) {
                $post->save();
            }

            $post->refresh();
        }

        if (!Storage::disk('public')->exists($post->option_one_image) ||
            !Storage::disk('public')->exists($post->option_two_image)) {
            Log::error('Images missing after post creation, skipping social media sharing', [
                'post_id' => $post->id,
                'option_one_exists' => Storage::disk('public')->exists($post->option_one_image),
                'option_two_exists' => Storage::disk('public')->exists($post->option_two_image)
            ]);
        } else {
            SharePostToSocialMedia::dispatch($post)->delay(now()->addSeconds(5));

            Log::info('Social media sharing job dispatched', [
                'post_id' => $post->id,
                'delay_seconds' => 5
            ]);
        }

        Log::channel('audit_trail')->info('Post created and passed all moderation.', array_merge($logContextBase, [
            'post_id' => $post->id,
            'question' => Str::limit($post->question, 100),
        ]));

        PingSearchEngines::dispatch();
        GoogleIndexingService::submitSitemap();
        return redirect()->route('home')->with('success', __('messages.post_created_successfully'));
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

    private function moderateTextWithGemini(string $textContent, string $contextLabel): array
    {
        $apiKey = Config::get('gemini.api_key');
        $promptTemplate = Config::get('gemini.prompt_template');

        if (!$apiKey || !$promptTemplate) {
            Log::error('Gemini text moderation config missing.', ['context' => $contextLabel]);
            return ['is_appropriate' => true, 'reason' => null, 'category' => 'UNCHECKED_CONFIG_ERROR', 'error' => 'Gemini config missing. Content allowed.'];
        }

        $currentLocale = App::getLocale();
        $languageNames = [
            'en' => 'English', 'ru' => 'Russian', 'uz' => 'Uzbek',
        ];
        $languageName = $languageNames[$currentLocale] ?? 'English';

        $intermediatePrompt = str_replace("{LANGUAGE_NAME}", $languageName, $promptTemplate);
        $finalPrompt = str_replace("{COMMENT_TEXT}", addslashes($textContent), $intermediatePrompt);

        $model = Config::get('gemini.model', 'gemini-1.5-flash');
        $apiUrl = rtrim(Config::get('gemini.api_url'), '/') . '/' . $model . ':generateContent?key=' . $apiKey;
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

    private function moderateImageWithGemini(UploadedFile $imageFile, string $contextLabel, array $postContext): array
    {
        $apiKey = Config::get('gemini.api_key');
        $promptTemplate = Config::get('gemini.image_prompt_template');

        if (!$apiKey || !$promptTemplate) {
            Log::error('Gemini image moderation config missing.', ['context' => $contextLabel]);
            return ['is_appropriate' => true, 'reason' => null, 'category' => 'UNCHECKED_CONFIG_ERROR', 'error' => 'Gemini image config missing. Image allowed.'];
        }

        $currentLocale = App::getLocale();
        $languageNames = [
            'en' => 'English', 'ru' => 'Russian', 'uz' => 'Uzbek',
        ];
        $languageName = $languageNames[$currentLocale] ?? 'English';

        $optionTitleForContext = ($contextLabel === 'option_one_image')
            ? $postContext['option_one_title']
            : $postContext['option_two_title'];

        $intermediatePrompt = str_replace(
            ['{LANGUAGE_NAME}', '{QUESTION_TEXT}', '{OPTION_TITLE_TEXT}'],
            [$languageName, addslashes($postContext['question']), addslashes($optionTitleForContext)],
            $promptTemplate
        );

        $finalPromptForImage = $intermediatePrompt;

        $model = Config::get('gemini.model', 'gemini-1.5-flash');
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
                        ['text' => $finalPromptForImage],
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

    private function processAndStoreImage(UploadedFile $uploadedFile, string $directory, string $baseFilename): array
    {
        $mainImageFilename = $baseFilename . '.webp';
        $mainImagePath = $directory . '/' . $mainImageFilename;

        $tempPath = $uploadedFile->getRealPath();
        $finalStoragePath = Storage::disk('public')->path($mainImagePath);

        $binaryPath = base_path('image_processor');

        if (is_executable($binaryPath)) {
            try {
                $command = sprintf(
                    '%s %s %s %d %d %d %d %d',
                    escapeshellcmd($binaryPath),
                    escapeshellarg($tempPath),            // <input>
                    escapeshellarg($finalStoragePath),       // <output_webp>
                    self::MAX_POST_IMAGE_WIDTH,          // <width>
                    self::MAX_POST_IMAGE_WIDTH,          // <height>
                    self::POST_IMAGE_QUALITY,            // <quality>
                    self::LQIP_WIDTH,                    // <lqip_width>
                    self::LQIP_QUALITY                   // <lqip_quality>
                );

                $lqipBase64 = exec($command, $output, $returnCode);

                if ($returnCode !== 0) {
                    throw new Exception('Custom C binary failed with code ' . $returnCode . '. Output: ' . implode("\n", $output));
                }

                $lqip = 'data:image/jpeg;base64,' . $lqipBase64;
                Log::info('Image processed with custom high-performance C binary.');

                return ['main' => $mainImagePath, 'lqip' => $lqip];

            } catch (Exception $e) {
                Log::error('Custom C binary processing failed. Falling back to PHP GD.', ['error' => $e->getMessage()]);
            }
        }

        Log::warning('Custom C binary not found or failed. Using standard PHP GD library.');
        $manager = new ImageManager(new GdDriver());
        $image = $manager->read($tempPath);

        $image->scaleDown(width: self::MAX_POST_IMAGE_WIDTH);
        $encodedMainImage = $image->encode(new WebpEncoder(quality: self::POST_IMAGE_QUALITY));
        Storage::disk('public')->put($mainImagePath, $encodedMainImage);

        $lqip = (string)$image->scale(width: self::LQIP_WIDTH)
            ->blur(5)
            ->encode(new JpegEncoder(quality: self::LQIP_QUALITY))
            ->toDataUri();

        return ['main' => $mainImagePath, 'lqip' => $lqip];
    }

    final public function create(): View
    {
        return view('posts.create', [
            'maxFileSizeKB' => self::MAX_POST_IMAGE_SIZE_KB,
            'maxFileSizeMB' => self::MAX_POST_IMAGE_SIZE_MB
        ]);
    }

    private function generateContextWithGemini(string $question, string $optionOne, string $optionTwo): ?string
    {
        $apiKey = Config::get('gemini.api_key');
        $promptTemplate = Config::get('gemini.context_generation_prompt');

        if (!$apiKey || !$promptTemplate) {
            Log::error('Gemini context generation config missing.');
            return null;
        }

        $prompt = str_replace(
            ['{QUESTION}', '{OPTION_ONE}', '{OPTION_TWO}'],
            [addslashes($question), addslashes($optionOne), addslashes($optionTwo)],
            $promptTemplate
        );

        $model = Config::get('gemini.model', 'gemini-1.5-flash');
        $apiUrl = rtrim(Config::get('gemini.api_url'), '/') . '/' . $model . ':generateContent?key=' . $apiKey;
        $payload = ['contents' => [['parts' => [['text' => $prompt]]]]];

        try {
            $response = Http::timeout(25)->post($apiUrl, $payload);
            if (!$response->successful()) {
                Log::error('Gemini context API request failed.', ['status' => $response->status(), 'body' => $response->body()]);
                return null;
            }
            $responseData = $response->json();
            return $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;
        } catch (Exception $e) {
            Log::error('Gemini context generation exception.', ['message' => $e->getMessage()]);
            return null;
        }
    }

    private function generateTagsWithGemini(string $question, string $optionOne, string $optionTwo): ?string
    {
        $apiKey = Config::get('gemini.api_key');
        $promptTemplate = Config::get('gemini.tag_generation_prompt');

        if (!$apiKey) {
            Log::error('Gemini API key is missing for tag generation.');
            return null;
        }

        $prompt = str_replace(
            ['{QUESTION}', '{OPTION_ONE}', '{OPTION_TWO}'],
            [addslashes($question), addslashes($optionOne), addslashes($optionTwo)],
            $promptTemplate
        );

        $model = Config::get('gemini.model', 'gemini-1.5-flash');
        $apiUrl = rtrim(Config::get('gemini.api_url'), '/') . '/' . $model . ':generateContent?key=' . $apiKey;
        $payload = ['contents' => [['parts' => [['text' => $prompt]]]]];

        try {
            $response = Http::timeout(20)->post($apiUrl, $payload);
            if (!$response->successful()) {
                Log::error('Gemini tags API request failed.', ['status' => $response->status(), 'body' => $response->body()]);
                return null;
            }
            $responseData = $response->json();
            $tags = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;
            return $tags ? str_replace(['#', '.', '"'], '', trim($tags)) : null;
        } catch (Exception $e) {
            Log::error('Gemini tags generation exception.', ['message' => $e->getMessage()]);
            return null;
        }
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
            Log::channel('audit_trail')->warning('Unauthorized post update attempt.', [
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

        foreach ($textFieldsToModerate as $field => $newContent) {
            if ($newContent !== $originalPostData[$field]) {
                // 1. Banned Words Check
                $bannedWordCheck = $this->checkForBannedWords($newContent, $field);
                if ($bannedWordCheck && !$bannedWordCheck['is_appropriate']) {
                    $moderationErrorMessage = __($bannedWordCheck['translation_key'], $bannedWordCheck['translation_params']);
                    Log::channel('audit_trail')->info('Post update rejected by local blacklist.', array_merge($logContextBase, ['field' => $field, 'reason_key' => $bannedWordCheck['translation_key'], 'category' => $bannedWordCheck['category']]));
                    return redirect()->back()->withErrors([$field => $moderationErrorMessage])->withInput();
                }

                // 2. Gemini Text Check
                if (!App::isLocal()) {
                    $geminiTextCheck = $this->moderateTextWithGemini($newContent, $field);
                    if (!$geminiTextCheck['is_appropriate']) {
                        $reasonText = $geminiTextCheck['reason'] ?? $geminiTextCheck['category'];
                        if (str_starts_with($geminiTextCheck['category'], 'ERROR_') || str_starts_with($geminiTextCheck['category'], 'UNCHECKED_')) {
                            $moderationErrorMessage = __('messages.error_post_moderation_system_issue', ['field' => $field]);
                            Log::warning('Gemini Text Moderation Service Error during post update.', array_merge($logContextBase, ['field' => $field, 'details' => $geminiTextCheck]));
                        } else {
                            $moderationErrorMessage = __('messages.error_post_content_inappropriate', ['field' => $field, 'reason' => $reasonText]);
                            Log::channel('audit_trail')->info('Post update rejected by Gemini text moderation.', array_merge($logContextBase, ['field' => $field, 'reason' => $reasonText, 'category' => $geminiTextCheck['category']]));
                        }
                        return redirect()->back()->withErrors([$field => $moderationErrorMessage])->withInput();
                    }
                }
            }
        }

        // --- Image Moderation for new/replaced images ---
        $imagesToModerateOnUpdate = [];
        if ($request->hasFile('option_one_image')) $imagesToModerateOnUpdate['option_one_image'] = $request->file('option_one_image');
        if ($request->hasFile('option_two_image')) $imagesToModerateOnUpdate['option_two_image'] = $request->file('option_two_image');

        // Create the context required for image moderation.
        $postContextForUpdate = [
            'question' => $request->input('question'),
            'option_one_title' => $request->input('option_one_title'),
            'option_two_title' => $request->input('option_two_title'),
        ];

        foreach ($imagesToModerateOnUpdate as $field => $imageFile) {
            // Moderate images
            if (!App::isLocal()) {
                // Pass the context to the moderation function.
                $geminiImageCheck = $this->moderateImageWithGemini($imageFile, $field, $postContextForUpdate);
                if (!$geminiImageCheck['is_appropriate']) {
                    $reasonText = $geminiImageCheck['reason'] ?? $geminiImageCheck['category'];
                    if (str_starts_with($geminiImageCheck['category'], 'ERROR_') || str_starts_with($geminiImageCheck['category'], 'UNCHECKED_')) {
                        $moderationErrorMessage = __('messages.error_post_image_moderation_system_issue', ['field' => $field]);
                        Log::warning('Gemini Image Moderation Service Error during post update.', array_merge($logContextBase, ['field' => $field, 'details' => $geminiImageCheck]));
                    } else {
                        $moderationErrorMessage = __('messages.error_post_image_inappropriate', ['field' => $field, 'reason' => $reasonText]);
                        Log::channel('audit_trail')->info('Post update rejected by Gemini image moderation.', array_merge($logContextBase, ['field' => $field, 'reason' => $reasonText, 'category' => $geminiImageCheck['category']]));
                    }
                    return redirect()->back()->withErrors([$field => $moderationErrorMessage])->withInput();
                }
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
        Log::channel('audit_trail')->info('Post updated and passed all moderation.', array_merge($logContextBase, ['updated_fields' => array_keys($data)]));
        PingSearchEngines::dispatch();
        return redirect()->route('profile.show', ['username' => $post->user->username])->with('success', __('messages.post_updated_successfully'));
    }

    final public function destroy(Post $post): RedirectResponse
    {
        $user = Auth::user();

        if (!$user || (int)$user->id !== (int)$post->user_id) {
            Log::warning('Authorization FAILED', [
                'checked_user_id' => $user ? $user->id : 'none',
                'post_owner_id' => $post->user_id
            ]);
            abort(403, __('messages.error_unauthorized_action'));
        }

        $postId = $post->id;
        $postQuestion = $post->question;
        $postOwnerId = (int)$post->user_id;
        $postOwnerUsername = $post->user->username;

        $filesToDelete = [];
        if ($post->option_one_image) {
            $filesToDelete[] = $post->option_one_image;
        }
        if ($post->option_one_image_placeholder) {
            $filesToDelete[] = $post->option_one_image_placeholder;
        }
        if (!empty($filesToDelete)) {
            Storage::disk('public')->delete($filesToDelete);
        }

        $filesToDelete = [];
        if ($post->option_two_image) {
            $filesToDelete[] = $post->option_two_image;
        }
        if ($post->option_two_image_placeholder) {
            $filesToDelete[] = $post->option_two_image_placeholder;
        }
        if (!empty($filesToDelete)) {
            Storage::disk('public')->delete($filesToDelete);
        }

        Vote::where('post_id', $post->id)->delete();
        $post->delete();

        Log::channel('audit_trail')->info('Post deleted.', [
            'deleter_user_id' => $user->id,
            'deleter_username' => $user->username,
            'deleted_post_id' => $postId,
            'original_post_question' => Str::limit($postQuestion, 100),
            'original_post_owner_id' => $postOwnerId,
            'original_post_owner_username' => $postOwnerUsername,
            'ip_address' => request()->ip(),
        ]);

        $previousUrl = url()->previous();
        $profileUrlOfPostOwner = route('profile.show', ['username' => $postOwnerUsername]);
        $currentUserId = (int)$user->id;

        if (str_contains($previousUrl, $profileUrlOfPostOwner)) {
            return redirect()->route('profile.show', ['username' => $postOwnerUsername])
                ->with('success', __('messages.post_deleted_successfully'));
        }

        PingSearchEngines::dispatch();
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
        $perPage = 15;

        if (!$queryTerm) {
            $posts = Post::query()->whereRaw('0 = 1')->paginate($perPage);
            return view('search.results', ['posts' => $posts, 'users' => collect(), 'queryTerm' => null]);
        }

        $soundexCode = soundex($queryTerm);

        // --- 1. GET CANDIDATE RESULTS ---
        $candidatePosts = Post::query()->withPostData()
            ->where(function (Builder $subQuery) use ($queryTerm, $soundexCode) {
                $subQuery->where('question', 'LIKE', "%{$queryTerm}%")
                    ->orWhereRaw('SOUNDEX(question) = ?', [$soundexCode])
                    ->orWhere('option_one_title', 'LIKE', "%{$queryTerm}%")
                    ->orWhereRaw('SOUNDEX(option_one_title) = ?', [$soundexCode])
                    ->orWhere('option_two_title', 'LIKE', "%{$queryTerm}%")
                    ->orWhereRaw('SOUNDEX(option_two_title) = ?', [$soundexCode])
                    ->orWhere('ai_generated_tags', 'LIKE', "%{$queryTerm}%")
                    ->orWhereHas('user', fn(Builder $q) => $q->where('username', 'LIKE', "%{$queryTerm}%"));
            })
            ->latest()
            ->limit(200)
            ->get();

        $candidateUsers = User::query()
            ->where(function (Builder $subQuery) use ($queryTerm, $soundexCode) {
                $subQuery->where('username', 'LIKE', "%{$queryTerm}%")
                    ->orWhereRaw('SOUNDEX(username) = ?', [$soundexCode]) // Check phonetic match
                    ->orWhere('first_name', 'LIKE', "%{$queryTerm}%")
                    ->orWhereRaw('SOUNDEX(first_name) = ?', [$soundexCode]);
            })
            ->limit(50)
            ->get();

        // --- 2. SCORE AND SORT CANDIDATES ---
        $sortedPosts = $this->levenshteinService->findBestMatches(
            $queryTerm,
            $candidatePosts,
            ['question', 'option_one_title', 'option_two_title', 'ai_generated_tags', 'user.username']
        );

        $sortedUsers = $this->levenshteinService->findBestMatches(
            $queryTerm,
            $candidateUsers,
            ['username', 'first_name', 'last_name']
        );

        // --- 3. MANUALLY PAGINATE THE SORTED POSTS ---
        $currentPage = Paginator::resolveCurrentPage('page');
        $currentPagePosts = $sortedPosts->slice(($currentPage - 1) * $perPage, $perPage);

        $posts = new \Illuminate\Pagination\LengthAwarePaginator(
            $currentPagePosts,
            $sortedPosts->count(),
            $perPage,
            $currentPage,
            ['path' => Paginator::resolveCurrentPath(), 'query' => $request->query()]
        );

        $this->attachUserVoteStatus($posts);

        // --- 4. HANDLE RESPONSE TYPE ---
        if ($request->expectsJson()) {
            return response()->json([
                'users' => $sortedUsers->take(10),
                'posts' => $posts
            ]);
        }

        return view('search.results', [
            'posts' => $posts,
            'users' => $sortedUsers->take(10),
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
