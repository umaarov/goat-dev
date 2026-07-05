<?php

namespace App\Services;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Content moderation.
 *
 *  - TEXT (comments, names, URLs) is moderated by DeepSeek (OpenAI-compatible
 *    chat completions, JSON mode).
 *  - IMAGES are moderated by Groq's vision model, because DeepSeek has no
 *    vision capability.
 *
 * Each provider is independently optional: when its key is missing the relevant
 * checks are skipped (content allowed), so the app keeps working unconfigured.
 */
class ModerationService
{
    // --- DeepSeek (text) ---
    private ?string $textApiKey;

    private string $textModel;

    private string $textApiUrl;

    private ?string $textPrompt;

    private ?string $commentPrompt;

    private ?string $urlPrompt;

    private bool $textConfigured;

    // --- Groq (image / vision) ---
    private ?string $imageApiKey;

    private ?string $imageModel;

    private ?string $imagePrompt;

    private string $imageApiUrl = 'https://api.groq.com/openai/v1/chat/completions';

    private bool $imageConfigured;

    public function __construct()
    {
        $this->textApiKey = Config::get('services.deepseek.api_key');
        $this->textModel = Config::get('services.deepseek.model', 'deepseek-chat');
        $this->textApiUrl = rtrim((string) Config::get('services.deepseek.base_url', 'https://api.deepseek.com'), '/').'/chat/completions';
        $this->textPrompt = Config::get('services.deepseek.prompts.text');
        $this->commentPrompt = Config::get('services.deepseek.prompts.comment');
        $this->urlPrompt = Config::get('services.deepseek.prompts.url');
        $this->textConfigured = ! empty($this->textApiKey) && ! empty($this->textModel);

        $this->imageApiKey = Config::get('services.groq.api_key');
        $this->imageModel = Config::get('services.groq.vision_model');
        $this->imagePrompt = Config::get('services.groq.prompts.image');
        $this->imageConfigured = ! empty($this->imageApiKey) && ! empty($this->imageModel);

        if (! $this->textConfigured) {
            Log::warning('ModerationService: DeepSeek text moderation is not configured. Text checks will be skipped (allowed).');
        }
    }

    public function moderateText(string $text, string $languageCode = 'en'): array
    {
        return $this->performTextCheck($text, $this->textPrompt, $languageCode, 'Strict Profile Check');
    }

    public function moderateComment(string $text, string $languageCode = 'en'): array
    {
        return $this->performTextCheck($text, $this->commentPrompt, $languageCode, 'Smart Comment Check');
    }

    public function moderateUrl(string $urlInput, string $languageCode = 'en'): array
    {
        if (! $this->textConfigured || empty(trim($urlInput))) {
            return ['is_appropriate' => true, 'reason' => null, 'category' => 'NONE'];
        }

        $allowedDomains = ['t.me', 'telegram.me', 'x.com', 'twitter.com', 'instagram.com', 'facebook.com', 'youtube.com', 'youtu.be', 'discord.gg'];
        $parsedHost = parse_url($urlInput, PHP_URL_HOST);
        $cleanHost = preg_replace('/^www\./', '', $parsedHost ?? '');

        if (in_array($cleanHost, $allowedDomains)) {
            return ['is_appropriate' => true, 'reason' => null, 'category' => 'SAFE_DOMAIN'];
        }

        if (! Str::startsWith($urlInput, ['http://', 'https://'])) {
            Log::warning("ModerationService: Non-HTTP URL detected: $urlInput");
        }

        $languageName = $this->getLanguageName($languageCode);
        $finalPrompt = $this->urlPrompt." (Respond in $languageName if inappropriate).";

        return $this->executeTextRequest([
            ['role' => 'system', 'content' => $finalPrompt],
            ['role' => 'user', 'content' => "Check URL: \"$urlInput\""],
        ], "URL: $urlInput");
    }

    public function moderateImage(UploadedFile $imageFile, string $languageCode = 'en'): array
    {
        if (! $this->imageConfigured) {
            return ['is_appropriate' => true, 'reason' => null, 'category' => 'NONE'];
        }

        $logContext = 'Image: '.$imageFile->getClientOriginalName();
        $languageName = $this->getLanguageName($languageCode);
        $finalPrompt = $this->imagePrompt." (Respond in $languageName if inappropriate).";

        try {
            $imageData = base64_encode(file_get_contents($imageFile->getRealPath()));
            $mimeType = $imageFile->getMimeType();

            $messages = [
                ['role' => 'system', 'content' => $finalPrompt],
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => 'Analyze this image.'],
                        ['type' => 'image_url', 'image_url' => ['url' => "data:$mimeType;base64,$imageData"]],
                    ],
                ],
            ];

            return $this->executeGroqVisionRequest($messages, $logContext);
        } catch (Exception $e) {
            Log::error("ModerationService: Image Processing Error for $logContext: ".$e->getMessage());

            return ['is_appropriate' => true, 'category' => 'EXCEPTION'];
        }
    }

    private function performTextCheck(string $text, ?string $promptSystem, string $languageCode, string $logTag): array
    {
        if (! $this->textConfigured || empty($promptSystem) || empty(trim($text))) {
            return ['is_appropriate' => true, 'reason' => null, 'category' => 'NONE'];
        }

        $logContext = "$logTag: ".Str::limit($text, 50);
        $languageName = $this->getLanguageName($languageCode);
        $finalPrompt = $promptSystem." (Analyze content in $languageName. If violating, respond in $languageName).";

        return $this->executeTextRequest([
            ['role' => 'system', 'content' => $finalPrompt],
            ['role' => 'user', 'content' => "Input: \"$text\""],
        ], $logContext);
    }

    private function getLanguageName(string $localeCode): string
    {
        $availableLocales = Config::get('app.available_locales', ['en' => 'English']);

        return $availableLocales[$localeCode] ?? 'English';
    }

    /**
     * Text moderation request (DeepSeek).
     */
    private function executeTextRequest(array $messages, string $logContext): array
    {
        return $this->executeChatRequest($this->textApiUrl, $this->textApiKey, $this->textModel, $messages, $logContext);
    }

    /**
     * Image moderation request (Groq vision).
     */
    private function executeGroqVisionRequest(array $messages, string $logContext): array
    {
        return $this->executeChatRequest($this->imageApiUrl, $this->imageApiKey, $this->imageModel, $messages, $logContext);
    }

    /**
     * Shared OpenAI-compatible chat-completions call + result parsing.
     * Used for both DeepSeek (text) and Groq (vision).
     */
    private function executeChatRequest(string $url, ?string $apiKey, ?string $model, array $messages, string $logContext): array
    {
        try {
            Log::debug("ModerationService: Sending Request for $logContext");

            $response = Http::withToken($apiKey)
                ->timeout(20)
                ->retry(2, 300, throw: false)
                ->post($url, [
                    'model' => $model,
                    'messages' => $messages,
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.1,
                ]);

            if ($response->failed()) {
                Log::error("ModerationService: API Failure ({$response->status()}) for $logContext: ".$response->body());

                return ['is_appropriate' => true, 'category' => 'API_ERROR'];
            }

            $jsonContent = $response->json('choices.0.message.content');

            if (! $jsonContent) {
                Log::error("ModerationService: Empty response content for $logContext");

                return ['is_appropriate' => true, 'category' => 'EMPTY_RESPONSE'];
            }

            $data = json_decode($jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error("ModerationService: JSON Parse Error for $logContext: ".json_last_error_msg());

                return ['is_appropriate' => true, 'category' => 'JSON_PARSE_ERROR'];
            }

            if (! isset($data['is_appropriate'])) {
                Log::error("ModerationService: Invalid Schema for $logContext", $data);

                return ['is_appropriate' => true, 'category' => 'SCHEMA_ERROR'];
            }

            return [
                'is_appropriate' => (bool) $data['is_appropriate'],
                'reason' => $data['reason_if_inappropriate'] ?? null,
                'category' => $data['violation_category'] ?? 'UNKNOWN',
            ];
        } catch (Exception $e) {
            Log::error("ModerationService: Critical Exception for $logContext: ".$e->getMessage());

            return ['is_appropriate' => true, 'category' => 'CRITICAL_EXCEPTION'];
        }
    }
}
