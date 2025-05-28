<?php

namespace App\Services;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ModerationService
{
    private string $apiKey;
    private string $apiUrl;
    private string $model;
    private string $textPromptTemplate;
    private string $imagePromptTemplate;
    private bool $isConfigured;
    private bool $useJsonMode;

    public function __construct()
    {
        $this->apiKey = Config::get('gemini.api_key');
        $this->apiUrl = Config::get('gemini.api_url');
        $this->model = Config::get('gemini.model');
        $this->textPromptTemplate = Config::get('gemini.prompt_template');
        $this->imagePromptTemplate = Config::get('gemini.image_prompt_template');
        $this->useJsonMode = Config::get('gemini.use_json_mode', true);

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

    private function extractJsonFromString(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        if (preg_match('/```json\s*(\{[\s\S]*?\})\s*```/s', $text, $matches)) {
            return $matches[1];
        }
        if (preg_match('/(\{[\s\S]*?\})/s', $text, $matches)) {
            return $matches[1];
        }
        if (Str::startsWith(trim($text), '{') && Str::endsWith(trim($text), '}')) {
            return trim($text);
        }
        return null;
    }

    private function parseModerationResult(mixed $apiResponseData, string $contextForLogging): array
    {
        $jsonStringToParse = null;

        if (!isset($apiResponseData['candidates'][0]['content']['parts'][0])) {
            Log::error("ModerationService: Unexpected API response structure for {$contextForLogging}. Missing 'candidates[0][content][parts][0]'.", ['response_data' => $apiResponseData]);
            return ['is_appropriate' => false, 'reason' => "Moderation service response invalid (structure). Context: {$contextForLogging}", 'category' => 'ERROR'];
        }

        $jsonStringToParse = $apiResponseData['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($jsonStringToParse === null) {
            Log::error("ModerationService: 'text' field missing in API response part for {$contextForLogging}.", ['part_data' => $apiResponseData['candidates'][0]['content']['parts'][0]]);
            return ['is_appropriate' => false, 'reason' => "Moderation service response invalid (missing text part). Context: {$contextForLogging}", 'category' => 'ERROR'];
        }

        Log::debug("ModerationService: Raw text content from API for {$contextForLogging}.", ['raw_text_content' => $jsonStringToParse]);

        $extractedJson = $this->extractJsonFromString($jsonStringToParse);

        if (!$extractedJson) {
            Log::error("ModerationService: Could not extract a valid JSON-like string from Gemini's response for {$contextForLogging}.", [
                'received_string' => $jsonStringToParse
            ]);
            $reason = Str::limit("Moderation analysis failed (extraction). Gemini's raw response: " . $jsonStringToParse, 150);
            return ['is_appropriate' => false, 'reason' => $reason, 'category' => 'ERROR'];
        }

        Log::debug("ModerationService: Extracted JSON string for {$contextForLogging}.", ['extracted_json' => $extractedJson]);
        $moderationResult = json_decode($extractedJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error("ModerationService: Failed to parse extracted JSON string for {$contextForLogging}.", [
                'json_error' => json_last_error_msg(),
                'json_string_attempted_to_parse' => $extractedJson,
                'original_api_text_part' => $jsonStringToParse
            ]);
            return ['is_appropriate' => false, 'reason' => "Moderation analysis failed (JSON parsing). Context: {$contextForLogging}", 'category' => 'ERROR'];
        }

        if (!isset($moderationResult['is_appropriate']) || !isset($moderationResult['violation_category'])) {
            Log::error("ModerationService: Parsed JSON is missing required keys ('is_appropriate' or 'violation_category') for {$contextForLogging}.", [
                'parsed_json' => $moderationResult,
                'original_extracted_json_string' => $extractedJson
            ]);
            return ['is_appropriate' => false, 'reason' => "Moderation analysis failed (schema mismatch). Context: {$contextForLogging}", 'category' => 'ERROR'];
        }

        return [
            'is_appropriate' => (bool)$moderationResult['is_appropriate'],
            'reason' => $moderationResult['reason_if_inappropriate'] ?? null,
            'category' => $moderationResult['violation_category'],
        ];
    }

    public function moderateText(string $text, string $languageCode = 'en'): array
    {
        if (!$this->isConfigured) {
            Log::warning('ModerationService: Service not configured. Allowing text content by default.');
            return ['is_appropriate' => true, 'reason' => null, 'category' => 'NONE'];
        }
        if (empty(trim($text))) {
            return ['is_appropriate' => true, 'reason' => null, 'category' => 'NONE'];
        }

        $languageName = $this->getLanguageName($languageCode);
        $prompt = str_replace(['{COMMENT_TEXT}', '{LANGUAGE_NAME}'], [$text, $languageName], $this->textPromptTemplate);

        $payload = ['contents' => [['parts' => [['text' => $prompt]]]]];
        if ($this->useJsonMode) {
            $payload['generationConfig'] = ['responseMimeType' => 'application/json'];
        }

        $url = rtrim($this->apiUrl, '/') . '/' . $this->model . ':generateContent?key=' . $this->apiKey;

        try {
            $logContext = "Text: " . Str::limit($text, 70);
            Log::debug("ModerationService: Sending text moderation request for {$logContext}", ['url' => $url, 'using_json_mode' => $this->useJsonMode]);
            $response = Http::timeout(30)->post($url, $payload);

            if ($response->failed()) {
                Log::error("ModerationService: API request failed for text moderation on {$logContext}", [
                    'status' => $response->status(), 'body' => $response->body()
                ]);
                return ['is_appropriate' => false, 'reason' => "Moderation service error (API). Context: {$logContext}", 'category' => 'ERROR'];
            }
            return $this->parseModerationResult($response->json(), $logContext);

        } catch (Exception $e) {
            $logContext = "Text: " . Str::limit($text, 70);
            Log::error("ModerationService: Exception during text moderation for {$logContext}", ['message' => $e->getMessage()]);
            return ['is_appropriate' => false, 'reason' => "Moderation system unavailable (exception). Context: {$logContext}", 'category' => 'EXCEPTION'];
        }
    }

    public function moderateImage(UploadedFile $imageFile, string $languageCode = 'en'): array
    {
        if (!$this->isConfigured) {
            Log::warning('ModerationService: Service not configured. Allowing image by default.');
            return ['is_appropriate' => true, 'reason' => null, 'category' => 'NONE'];
        }

        $languageName = $this->getLanguageName($languageCode);
        $prompt = str_replace('{LANGUAGE_NAME}', $languageName, $this->imagePromptTemplate);

        $imageData = base64_encode(file_get_contents($imageFile->getRealPath()));
        $imageMimeType = $imageFile->getMimeType();

        $payload = [
            'contents' => [[
                'parts' => [
                    ['text' => $prompt],
                    ['inline_data' => ['mime_type' => $imageMimeType, 'data' => $imageData]]
                ]
            ]],
        ];
        if ($this->useJsonMode) {
            $payload['generationConfig'] = ['responseMimeType' => 'application/json'];
        }

        $url = rtrim($this->apiUrl, '/') . '/' . $this->model . ':generateContent?key=' . $this->apiKey;

        try {
            $logContext = "Image: " . $imageFile->getClientOriginalName();
            Log::debug("ModerationService: Sending image moderation request for {$logContext}", ['url' => $url, 'using_json_mode' => $this->useJsonMode]);
            $response = Http::timeout(45)->post($url, $payload);

            if ($response->failed()) {
                Log::error("ModerationService: API request failed for image moderation on {$logContext}", [
                    'status' => $response->status(), 'body' => $response->body()
                ]);
                return ['is_appropriate' => false, 'reason' => "Moderation service error (API). Context: {$logContext}", 'category' => 'ERROR'];
            }
            return $this->parseModerationResult($response->json(), $logContext);

        } catch (Exception $e) {
            $logContext = "Image: " . $imageFile->getClientOriginalName();
            Log::error("ModerationService: Exception during image moderation for {$logContext}", ['message' => $e->getMessage()]);
            return ['is_appropriate' => false, 'reason' => "Image moderation system unavailable (exception). Context: {$logContext}", 'category' => 'EXCEPTION'];
        }
    }
}
