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
    private string $urlPromptTemplate;
    private bool $isConfigured;
    private bool $useJsonMode;

    public function __construct()
    {
        $this->apiKey = Config::get('gemini.api_key');
        $this->apiUrl = Config::get('gemini.api_url');
        $this->model = Config::get('gemini.model');
        $this->textPromptTemplate = Config::get('gemini.prompt_template');
        $this->imagePromptTemplate = Config::get('gemini.image_prompt_template');
        $this->urlPromptTemplate = Config::get('gemini.url_prompt_template');
        $this->useJsonMode = Config::get('gemini.use_json_mode', true);

        $this->isConfigured = !empty($this->apiKey) &&
            !empty($this->apiUrl) &&
            !empty($this->model) &&
            !empty($this->textPromptTemplate) &&
            !empty($this->imagePromptTemplate) &&
            !empty($this->urlPromptTemplate);

        if (!$this->isConfigured) {
            Log::error('ModerationService: Gemini configuration is missing or incomplete. Moderation capabilities will be limited or permissive.');
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
        if (preg_match('/(\{((?:[^{}]++|\{(?1)\})++)\})/s', $text, $matches)) {
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
            return ['is_appropriate' => false, 'reason' => "Moderation service response invalid (structure). Context: {$contextForLogging}", 'category' => 'ERROR_API_RESPONSE_STRUCTURE'];
        }

        $jsonStringToParse = $apiResponseData['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($jsonStringToParse === null) {
            Log::error("ModerationService: 'text' field missing in API response part for {$contextForLogging}.", ['part_data' => $apiResponseData['candidates'][0]['content']['parts'][0]]);
            return ['is_appropriate' => false, 'reason' => "Moderation service response invalid (missing text part). Context: {$contextForLogging}", 'category' => 'ERROR_API_MISSING_TEXT'];
        }

        Log::debug("ModerationService: Raw text content from API for {$contextForLogging}.", ['raw_text_content' => $jsonStringToParse]);

        $extractedJson = $this->extractJsonFromString($jsonStringToParse);

        if (!$extractedJson) {
            Log::error("ModerationService: Could not extract a valid JSON-like string from Gemini's response for {$contextForLogging}.", [
                'received_string' => $jsonStringToParse
            ]);
            $reason = Str::limit("Moderation analysis failed (JSON extraction). Gemini's raw response: " . $jsonStringToParse, 150);
            return ['is_appropriate' => false, 'reason' => $reason, 'category' => 'ERROR_JSON_EXTRACTION'];
        }

        Log::debug("ModerationService: Extracted JSON string for {$contextForLogging}.", ['extracted_json' => $extractedJson]);
        $decodedJson = json_decode($extractedJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error("ModerationService: Failed to parse extracted JSON string for {$contextForLogging}.", [
                'json_error' => json_last_error_msg(),
                'json_string_attempted_to_parse' => $extractedJson,
                'original_api_text_part' => $jsonStringToParse
            ]);
            return ['is_appropriate' => false, 'reason' => "Moderation analysis failed (JSON parsing). Context: {$contextForLogging}", 'category' => 'ERROR_JSON_PARSE'];
        }

        $moderationData = null;
        if (isset($decodedJson['is_appropriate']) && isset($decodedJson['violation_category'])) {
            $moderationData = $decodedJson;
        } elseif (isset($decodedJson['properties']) && is_array($decodedJson['properties']) &&
            isset($decodedJson['properties']['is_appropriate']) && isset($decodedJson['properties']['violation_category'])) {
            $moderationData = $decodedJson['properties'];
        }

        if ($moderationData === null) {
            Log::error("ModerationService: Parsed JSON is missing required keys ('is_appropriate' or 'violation_category') at expected locations for {$contextForLogging}.", [
                'parsed_json' => $decodedJson, // Log the original full parsed structure
                'original_extracted_json_string' => $extractedJson
            ]);
            return ['is_appropriate' => false, 'reason' => "Moderation analysis failed (schema mismatch). Context: {$contextForLogging}", 'category' => 'ERROR_SCHEMA_MISMATCH'];
        }

        return [
            'is_appropriate' => (bool)$moderationData['is_appropriate'],
            'reason' => $moderationData['reason_if_inappropriate'] ?? null,
            'category' => $moderationData['violation_category'],
            // 'was_nested' => ($moderationData !== $decodedJson)
        ];
    }

    public function moderateText(string $text, string $languageCode = 'en'): array
    {
        if (!$this->isConfigured || empty($this->textPromptTemplate)) {
            Log::warning('ModerationService: Service not configured for text or text prompt missing. Allowing text content by default.', ['text_snippet' => Str::limit($text, 50)]);
            return ['is_appropriate' => true, 'reason' => null, 'category' => 'UNCHECKED_CONFIG'];
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

        $requestUrl = rtrim($this->apiUrl, '/') . '/' . $this->model . ':generateContent?key=' . $this->apiKey; // Renamed for clarity

        try {
            $logContext = "Text: " . Str::limit($text, 70);
            Log::debug("ModerationService: Sending text moderation request for {$logContext}", ['url' => $requestUrl, 'using_json_mode' => $this->useJsonMode]);
            $response = Http::timeout(30)->post($requestUrl, $payload); // Increased timeout slightly

            if ($response->failed()) {
                Log::error("ModerationService: API request failed for text moderation on {$logContext}", [
                    'status' => $response->status(), 'body' => $response->body()
                ]);
                return ['is_appropriate' => false, 'reason' => "Moderation service error (API status: {$response->status()}). Context: {$logContext}", 'category' => 'ERROR_API_REQUEST'];
            }
            return $this->parseModerationResult($response->json(), $logContext);

        } catch (Exception $e) {
            $logContext = "Text: " . Str::limit($text, 70);
            Log::error("ModerationService: Exception during text moderation for {$logContext}", ['message' => $e->getMessage(), 'trace' => Str::limit($e->getTraceAsString(), 300)]);
            return ['is_appropriate' => false, 'reason' => "Moderation system unavailable (exception). Context: {$logContext}", 'category' => 'ERROR_EXCEPTION'];
        }
    }

    public function moderateImage(UploadedFile $imageFile, string $languageCode = 'en'): array
    {
        if (!$this->isConfigured || empty($this->imagePromptTemplate)) {
            Log::warning('ModerationService: Service not configured for images or image prompt missing. Allowing image by default.', ['filename' => $imageFile->getClientOriginalName()]);
            return ['is_appropriate' => true, 'reason' => null, 'category' => 'UNCHECKED_CONFIG'];
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

        $requestUrl = rtrim($this->apiUrl, '/') . '/' . $this->model . ':generateContent?key=' . $this->apiKey;

        try {
            $logContext = "Image: " . $imageFile->getClientOriginalName();
            Log::debug("ModerationService: Sending image moderation request for {$logContext}", ['url' => $requestUrl, 'using_json_mode' => $this->useJsonMode]);
            $response = Http::timeout(45)->post($requestUrl, $payload);

            if ($response->failed()) {
                Log::error("ModerationService: API request failed for image moderation on {$logContext}", [
                    'status' => $response->status(), 'body' => $response->body()
                ]);
                return ['is_appropriate' => false, 'reason' => "Image moderation service error (API status: {$response->status()}). Context: {$logContext}", 'category' => 'ERROR_API_REQUEST'];
            }
            return $this->parseModerationResult($response->json(), $logContext);

        } catch (Exception $e) {
            $logContext = "Image: " . $imageFile->getClientOriginalName();
            Log::error("ModerationService: Exception during image moderation for {$logContext}", ['message' => $e->getMessage(), 'trace' => Str::limit($e->getTraceAsString(), 300)]);
            return ['is_appropriate' => false, 'reason' => "Image moderation system unavailable (exception). Context: {$logContext}", 'category' => 'ERROR_EXCEPTION'];
        }
    }

    public function moderateUrl(string $urlInput, string $languageCode = 'en'): array
    {
        if (!$this->isConfigured || empty($this->urlPromptTemplate)) {
            Log::warning('ModerationService: Service not configured for URLs or URL prompt missing. Allowing URL by default.', ['url' => $urlInput]);
            return ['is_appropriate' => true, 'reason' => null, 'category' => 'UNCHECKED_CONFIG'];
        }
        if (empty(trim($urlInput))) {
            return ['is_appropriate' => true, 'reason' => null, 'category' => 'NONE'];
        }

        if (!Str::startsWith($urlInput, ['http://', 'https://'])) {
            Log::warning("ModerationService: moderateUrl called with a non-HTTP(S) URL: {$urlInput}. This may fail or be misinterpreted by the API.");
        }

        $languageName = $this->getLanguageName($languageCode);
        $prompt = str_replace(['{URL_TEXT}', '{LANGUAGE_NAME}'], [$urlInput, $languageName], $this->urlPromptTemplate);

        $payload = ['contents' => [['parts' => [['text' => $prompt]]]]];
        if ($this->useJsonMode) {
            $payload['generationConfig'] = ['responseMimeType' => 'application/json'];
        }

        $requestUrl = rtrim($this->apiUrl, '/') . '/' . $this->model . ':generateContent?key=' . $this->apiKey;

        try {
            $logContext = "URL: " . Str::limit($urlInput, 100);
            Log::debug("ModerationService: Sending URL moderation request for {$logContext}", ['url_param' => $requestUrl, 'using_json_mode' => $this->useJsonMode]);
            $response = Http::timeout(30)->post($requestUrl, $payload);

            if ($response->failed()) {
                Log::error("ModerationService: API request failed for URL moderation on {$logContext}", [
                    'status' => $response->status(), 'body' => $response->body()
                ]);
                return ['is_appropriate' => false, 'reason' => "URL moderation service error (API status: {$response->status()}). Context: {$logContext}", 'category' => 'ERROR_API_REQUEST'];
            }
            return $this->parseModerationResult($response->json(), $logContext);

        } catch (Exception $e) {
            $logContext = "URL: " . Str::limit($urlInput, 100);
            Log::error("ModerationService: Exception during URL moderation for {$logContext}", ['message' => $e->getMessage(), 'trace' => Str::limit($e->getTraceAsString(), 300)]);
            return ['is_appropriate' => false, 'reason' => "URL moderation system unavailable (exception). Context: {$logContext}", 'category' => 'ERROR_EXCEPTION'];
        }
    }
}
