<?php

namespace App\Services;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AI analysis & enrichment for posts (Groq).
 *
 * Extracted verbatim from PostController so the web and the mobile API share
 * one implementation:
 *  - analyzeText(): the "master task" — moderates text AND generates the
 *    contextual blurb + search tags in a single Groq call.
 *  - moderateImages(): context-aware vision moderation of both option images.
 *  - checkLocalBannedWords(): cheap local blacklist pre-check.
 */
class PostEnrichmentService
{
    /**
     * Whether the text "master task" (moderation + context + tags) can run.
     * This is the DeepSeek text model.
     */
    public function isConfigured(): bool
    {
        return ! empty(config('services.deepseek.api_key'));
    }

    /**
     * Whether image (vision) moderation can run. DeepSeek has no vision model,
     * so post images are still moderated by Groq.
     */
    public function imageModerationConfigured(): bool
    {
        return ! empty(config('services.groq.api_key')) && ! empty(config('services.groq.vision_model'));
    }

    public function checkLocalBannedWords(array $inputs): ?array
    {
        $bannedWordsString = env('GEMINI_BANNED_WORDS_UZ');
        if (empty($bannedWordsString)) {
            return null;
        }

        $bannedWords = explode(',', strtolower($bannedWordsString));
        $fieldsToCheck = ['question', 'option_one_title', 'option_two_title'];

        foreach ($fieldsToCheck as $field) {
            if (! isset($inputs[$field])) {
                continue;
            }
            $lowerContent = strtolower($inputs[$field]);
            foreach ($bannedWords as $word) {
                $word = trim($word);
                if (! empty($word) && str_contains($lowerContent, $word)) {
                    return [$field => __('messages.error_post_content_prohibited_language')];
                }
            }
        }

        return null;
    }

    /**
     * Moderate text and generate context + tags in one call (DeepSeek).
     *
     * @return array{is_safe?:bool,violation_field?:string,moderation_reason?:string,generated_context?:string,generated_tags?:string}
     */
    public function analyzeText(string $q, string $o1, string $o2): array
    {
        $apiKey = config('services.deepseek.api_key');
        $language = App::getLocale() === 'uz' ? 'Uzbek' : (App::getLocale() === 'ru' ? 'Russian' : 'English');

        $prompt = str_replace(
            ['{QUESTION}', '{OPTION_ONE}', '{OPTION_TWO}', '{LANGUAGE}'],
            [addslashes($q), addslashes($o1), addslashes($o2), $language],
            (string) config('services.deepseek.prompts.master')
        );

        try {
            $url = rtrim((string) config('services.deepseek.base_url', 'https://api.deepseek.com'), '/').'/chat/completions';
            $response = Http::withToken($apiKey)->post($url, [
                'model' => config('services.deepseek.model', 'deepseek-chat'),
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.3,
            ]);

            if (! $response->successful()) {
                Log::error('DeepSeek API Error: '.$response->body());

                return ['is_safe' => true];
            }

            $content = $response->json('choices.0.message.content');
            $data = json_decode($content, true);

            if (isset($data['analysis_thought_process'])) {
                Log::info('AI Decision Logic: '.$data['analysis_thought_process']);
            }

            return $data ?? ['is_safe' => true];
        } catch (Exception $e) {
            Log::error('DeepSeek Exception: '.$e->getMessage());

            return ['is_safe' => true];
        }
    }

    /**
     * Context-aware vision moderation of both option images.
     *
     * @return array{safe?:bool,violation_source?:string,reason?:string}
     */
    public function moderateImages(UploadedFile $img1, UploadedFile $img2, array $textContext): array
    {
        $apiKey = env('GROQ_API_KEY');
        $language = App::getLocale() === 'uz' ? 'Uzbek' : (App::getLocale() === 'ru' ? 'Russian' : 'English');

        $promptRaw = env('GROQ_IMAGE_PROMPT');
        $prompt = str_replace(
            ['{QUESTION}', '{OPTION_ONE}', '{OPTION_TWO}', '{LANGUAGE}'],
            [
                addslashes($textContext['question']),
                addslashes($textContext['option_one_title']),
                addslashes($textContext['option_two_title']),
                $language,
            ],
            $promptRaw
        );

        $b64_1 = base64_encode(file_get_contents($img1->getRealPath()));
        $b64_2 = base64_encode(file_get_contents($img2->getRealPath()));

        try {
            $response = Http::withToken($apiKey)->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => env('GROQ_VISION_MODEL'),
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $prompt],
                            ['type' => 'image_url', 'image_url' => ['url' => 'data:'.$img1->getMimeType().";base64,$b64_1"]],
                            ['type' => 'image_url', 'image_url' => ['url' => 'data:'.$img2->getMimeType().";base64,$b64_2"]],
                        ],
                    ],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.1,
            ]);

            if (! $response->successful()) {
                Log::error('Groq Vision Error: '.$response->body());

                return ['safe' => true];
            }

            $data = json_decode($response->json('choices.0.message.content'), true);

            if (isset($data['analysis'])) {
                Log::info('AI Vision Logic: '.$data['analysis']);
            }

            return $data ?? ['safe' => true];
        } catch (Exception $e) {
            Log::error('Groq Vision Exception: '.$e->getMessage());

            return ['safe' => true];
        }
    }
}
