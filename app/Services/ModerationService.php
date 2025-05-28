<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str; // Import Str
use Exception;

class ModerationService
{
    private string $apiKey;
    private string $apiUrl;
    private string $model;
    private string $textPromptTemplate;
    private string $imagePromptTemplate;
    private bool $isConfigured;

    public function __construct()
    {
        $this->apiKey = Config::get('gemini.api_key');
        $this->apiUrl = Config::get('gemini.api_url');
        $this->model = Config::get('gemini.model');
        $this->textPromptTemplate = Config::get('gemini.prompt_template');
        $this->imagePromptTemplate = Config::get('gemini.image_prompt_template');

        $this->isConfigured = !empty($this->apiKey) &&
            !empty($this->apiUrl) &&
            !empty($this->model) &&
            !empty($this->textPromptTemplate) &&
            !empty($this->imagePromptTemplate);

        if (!$this->isConfigured) {
            Log::error('ModerationService: Gemini configuration is missing or incomplete. Moderation will be permissive.');
        }
    }

    private function getLanguageName(string $localeCode): string
    {
        $availableLocales = Config::get('app.available_locales', ['en' => 'English']);
        return $availableLocales[$localeCode] ?? 'English';
    }

    public function moderateText(string $text, string $languageCode = 'en'): array
    {
        if (!$this->isConfigured) {
            Log::warning('ModerationService: Service not configured. Allowing text content by default due to config issue.');
            return ['is_appropriate' => true, 'reason' => null, 'category' => 'NONE'];
        }
        if (empty(trim($text))) {
            return ['is_appropriate' => true, 'reason' => null, 'category' => 'NONE'];
        }

        $languageName = $this->getLanguageName($languageCode);
        $prompt = str_replace(['{COMMENT_TEXT}', '{LANGUAGE_NAME}'], [addslashes($text), $languageName], $this->textPromptTemplate);

        // Ensure the model specified in gemini.model is suitable for text generation.
        // Models like 'gemini-pro' or 'gemini-1.5-flash-latest' can be used.
        $modelToUse = $this->model;

        $payload = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            // To ensure the output is JSON, if the model supports it directly:
            // 'generationConfig' => [
            //     'responseMimeType' => 'application/json',
            // ]
            // If not, the prompt must reliably ask for JSON, and we parse the text part.
        ];

        $url = rtrim($this->apiUrl, '/') . '/' . $modelToUse . ':generateContent?key=' . $this->apiKey;

        try {
            Log::debug('ModerationService: Sending text moderation request.', ['url' => $url, 'text_preview' => Str::limit($text, 100)]);
            $response = Http::timeout(30)->post($url, $payload);

            if ($response->failed()) {
                Log::error('ModerationService: API request failed for text moderation.', [
                    'status' => $response->status(), 'body' => $response->body(), 'text_preview' => Str::limit($text, 50)
                ]);
                return ['is_appropriate' => false, 'reason' => 'Moderation service error (API).', 'category' => 'ERROR'];
            }

            $responseData = $response->json();
            Log::debug('ModerationService: Received text moderation response.', ['response' => $responseData]);

            $moderationResultJson = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if (!$moderationResultJson) {
                Log::error('ModerationService: Could not extract moderation JSON from Gemini response for text.', ['response_data' => $responseData]);
                return ['is_appropriate' => false, 'reason' => 'Moderation service response invalid (structure).', 'category' => 'ERROR'];
            }

            $moderationResult = json_decode($moderationResultJson, true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($moderationResult['is_appropriate'])) {
                Log::error('ModerationService: Failed to parse JSON from Gemini or missing "is_appropriate" key for text.', [
                    'json_error' => json_last_error_msg(), 'received_string' => $moderationResultJson
                ]);
                return ['is_appropriate' => false, 'reason' => 'Moderation analysis failed (parsing).', 'category' => 'ERROR'];
            }

            return [
                'is_appropriate' => (bool) $moderationResult['is_appropriate'],
                'reason' => $moderationResult['reason_if_inappropriate'] ?? null,
                'category' => $moderationResult['violation_category'] ?? 'OTHER',
            ];

        } catch (Exception $e) {
            Log::error('ModerationService: Exception during text moderation.', [
                'message' => $e->getMessage(), 'text_preview' => Str::limit($text, 50)
            ]);
            return ['is_appropriate' => false, 'reason' => 'Moderation system unavailable (exception).', 'category' => 'EXCEPTION'];
        }
    }

    public function moderateImage(UploadedFile $imageFile, string $languageCode = 'en'): array
    {
        if (!$this->isConfigured) {
            Log::warning('ModerationService: Service not configured. Allowing image by default due to config issue.');
            return ['is_appropriate' => true, 'reason' => null, 'category' => 'NONE'];
        }

        $languageName = $this->getLanguageName($languageCode);
        $prompt = str_replace('{LANGUAGE_NAME}', $languageName, $this->imagePromptTemplate);

        // Ensure the model specified in gemini.model is multimodal (e.g., 'gemini-pro-vision', 'gemini-1.5-flash-latest').
        $modelToUse = $this->model;

        $imageData = base64_encode(file_get_contents($imageFile->getRealPath()));
        $imageMimeType = $imageFile->getMimeType();

        $payload = [
            'contents' => [[
                'parts' => [
                    ['text' => $prompt],
                    ['inline_data' => ['mime_type' => $imageMimeType, 'data' => $imageData]]
                ]
            ]],
            // 'generationConfig' => [ // Optional: ensure JSON output if model supports
            //     'responseMimeType' => 'application/json',
            // ]
        ];

        $url = rtrim($this->apiUrl, '/') . '/' . $modelToUse . ':generateContent?key=' . $this->apiKey;

        try {
            Log::debug('ModerationService: Sending image moderation request.', ['url' => $url, 'filename' => $imageFile->getClientOriginalName()]);
            $response = Http::timeout(45)->post($url, $payload);

            if ($response->failed()) {
                Log::error('ModerationService: API request failed for image moderation.', [
                    'status' => $response->status(), 'body' => $response->body(), 'filename' => $imageFile->getClientOriginalName()
                ]);
                return ['is_appropriate' => false, 'reason' => 'Moderation service error (API).', 'category' => 'ERROR'];
            }

            $responseData = $response->json();
            Log::debug('ModerationService: Received image moderation response.', ['response' => $responseData]);

            $moderationResultJson = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if (!$moderationResultJson) {
                Log::error('ModerationService: Could not extract moderation JSON from Gemini response for image.', ['response_data' => $responseData]);
                return ['is_appropriate' => false, 'reason' => 'Moderation service response invalid (structure).', 'category' => 'ERROR'];
            }

            $moderationResult = json_decode($moderationResultJson, true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($moderationResult['is_appropriate'])) {
                Log::error('ModerationService: Failed to parse JSON from Gemini or missing "is_appropriate" key for image.', [
                    'json_error' => json_last_error_msg(), 'received_string' => $moderationResultJson
                ]);
                return ['is_appropriate' => false, 'reason' => 'Image moderation analysis failed (parsing).', 'category' => 'ERROR'];
            }

            return [
                'is_appropriate' => (bool) $moderationResult['is_appropriate'],
                'reason' => $moderationResult['reason_if_inappropriate'] ?? null,
                'category' => $moderationResult['violation_category'] ?? 'OTHER_IMAGE',
            ];

        } catch (Exception $e) {
            Log::error('ModerationService: Exception during image moderation.', [
                'message' => $e->getMessage(), 'filename' => $imageFile->getClientOriginalName()
            ]);
            return ['is_appropriate' => false, 'reason' => 'Image moderation system unavailable (exception).', 'category' => 'EXCEPTION'];
        }
    }
}
