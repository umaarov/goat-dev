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

    private string $textPrompt;
    private string $imagePrompt;
    private string $urlPrompt;

    private string $commentPrompt;
    private bool $isConfigured;

    public function __construct()
    {
        $this->apiKey = Config::get('services.groq.api_key');
        $this->model = Config::get('services.groq.model');

        $this->apiUrl = 'https://api.groq.com/openai/v1/chat/completions';

        $this->textPrompt = Config::get('services.groq.prompts.text');
        $this->imagePrompt = Config::get('services.groq.prompts.image');
        $this->urlPrompt = Config::get('services.groq.prompts.url');

        $this->commentPrompt = Config::get('services.groq.prompts.comment');

        $this->isConfigured = !empty($this->apiKey) && !empty($this->model);

        if (!$this->isConfigured) {
            Log::error('ModerationService: Groq configuration is missing. Moderation will be skipped (Allowed).');
        }
    }

    public function moderateText(string $text, string $languageCode = 'en'): array
    {
        return $this->performTextCheck($text, $this->textPrompt, $languageCode, 'Strict Profile Check');
    }

    private function performTextCheck(string $text, string $promptSystem, string $languageCode, string $logTag): array
    {
        if (!$this->isConfigured || empty(trim($text))) {
            return ['is_appropriate' => true, 'reason' => null, 'category' => 'NONE'];
        }

        $logContext = "$logTag: " . Str::limit($text, 50);
        $languageName = $this->getLanguageName($languageCode);

        $finalPrompt = $promptSystem . " (Analyze content in $languageName. If violating, respond in $languageName).";

        return $this->executeGroqRequest([
            ['role' => 'system', 'content' => $finalPrompt],
            ['role' => 'user', 'content' => "Input: \"$text\""]
        ], $logContext);
    }

    private function getLanguageName(string $localeCode): string
    {
        $availableLocales = Config::get('app.available_locales', ['en' => 'English']);
        return $availableLocales[$localeCode] ?? 'English';
    }

    private function executeGroqRequest(array $messages, string $logContext): array
    {
        try {
            Log::debug("ModerationService: Sending Request for $logContext");

            $response = Http::withToken($this->apiKey)
                ->timeout(20)
                ->post($this->apiUrl, [
                    'model' => $this->model,
                    'messages' => $messages,
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.1,
                ]);

            if ($response->failed()) {
                Log::error("ModerationService: API Failure ({$response->status()}) for $logContext: " . $response->body());
                return ['is_appropriate' => true, 'category' => 'API_ERROR'];
            }

            $jsonContent = $response->json('choices.0.message.content');

            if (!$jsonContent) {
                Log::error("ModerationService: Empty response content for $logContext");
                return ['is_appropriate' => true, 'category' => 'EMPTY_RESPONSE'];
            }

            $data = json_decode($jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error("ModerationService: JSON Parse Error for $logContext: " . json_last_error_msg());
                return ['is_appropriate' => true, 'category' => 'JSON_PARSE_ERROR'];
            }

            if (!isset($data['is_appropriate'])) {
                Log::error("ModerationService: Invalid Schema for $logContext", $data);
                return ['is_appropriate' => true, 'category' => 'SCHEMA_ERROR'];
            }

            return [
                'is_appropriate' => (bool)$data['is_appropriate'],
                'reason' => $data['reason_if_inappropriate'] ?? null,
                'category' => $data['violation_category'] ?? 'UNKNOWN'
            ];

        } catch (Exception $e) {
            Log::error("ModerationService: Critical Exception for $logContext: " . $e->getMessage());
            return ['is_appropriate' => true, 'category' => 'CRITICAL_EXCEPTION'];
        }
    }

    public function moderateComment(string $text, string $languageCode = 'en'): array
    {
        return $this->performTextCheck($text, $this->commentPrompt, $languageCode, 'Smart Comment Check');
    }

    public function moderateImage(UploadedFile $imageFile, string $languageCode = 'en'): array
    {
        if (!$this->isConfigured) {
            return ['is_appropriate' => true, 'reason' => null, 'category' => 'NONE'];
        }

        $logContext = "Image: " . $imageFile->getClientOriginalName();
        $languageName = $this->getLanguageName($languageCode);
        $finalPrompt = $this->imagePrompt . " (Respond in $languageName if inappropriate).";

        try {
            $imageData = base64_encode(file_get_contents($imageFile->getRealPath()));
            $mimeType = $imageFile->getMimeType();

            $messages = [
                ['role' => 'system', 'content' => $finalPrompt],
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => 'Analyze this image.'],
                        [
                            'type' => 'image_url',
                            'image_url' => ['url' => "data:$mimeType;base64,$imageData"]
                        ]
                    ]
                ]
            ];

            return $this->executeGroqRequest($messages, $logContext);

        } catch (Exception $e) {
            Log::error("ModerationService: Image Processing Error for $logContext: " . $e->getMessage());
            return ['is_appropriate' => true, 'category' => 'EXCEPTION'];
        }
    }

    public function moderateUrl(string $urlInput, string $languageCode = 'en'): array
    {
        if (!$this->isConfigured || empty(trim($urlInput))) {
            return ['is_appropriate' => true, 'reason' => null, 'category' => 'NONE'];
        }

        $allowedDomains = ['t.me', 'telegram.me', 'x.com', 'twitter.com', 'instagram.com', 'facebook.com', 'youtube.com', 'youtu.be', 'discord.gg'];
        $parsedHost = parse_url($urlInput, PHP_URL_HOST);

        $cleanHost = preg_replace('/^www\./', '', $parsedHost ?? '');

        if (in_array($cleanHost, $allowedDomains)) {
            return ['is_appropriate' => true, 'reason' => null, 'category' => 'SAFE_DOMAIN'];
        }

        $logContext = "URL: $urlInput";

        if (!Str::startsWith($urlInput, ['http://', 'https://'])) {
            Log::warning("ModerationService: Non-HTTP URL detected: $urlInput");
        }

        $languageName = $this->getLanguageName($languageCode);
        $finalPrompt = $this->urlPrompt . " (Respond in $languageName if inappropriate).";

        return $this->executeGroqRequest([
            ['role' => 'system', 'content' => $finalPrompt],
            ['role' => 'user', 'content' => "Check URL: \"$urlInput\""]
        ], $logContext);
    }
}
