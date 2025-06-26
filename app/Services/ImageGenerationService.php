<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ImageGenerationService
{
    protected string $apiUrl;
    protected string $accountId;
    protected string $apiToken;
    protected string $model;

    public function __construct()
    {
        $this->accountId = config('cloudflare.account_id');
        $this->apiToken = config('cloudflare.api_token');
        $this->model = config('cloudflare.ai_model');
        $this->apiUrl = "https://api.cloudflare.com/client/v4/accounts/{$this->accountId}/ai/run/{$this->model}";
    }

    public function generateImageFromPrompt(string $prompt): ?string
    {
        if (empty($this->accountId) || empty($this->apiToken)) {
            Log::error('Cloudflare AI credentials are not configured.');
            throw new Exception('Image generation service is not configured.');
        }

        $response = Http::withToken($this->apiToken)
            ->timeout(60)
            ->post($this->apiUrl, [
                'prompt' => $prompt,
                'num_steps' => 20
            ]);

        if ($response->failed()) {
            Log::error('Cloudflare AI API request failed.', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);
            throw new Exception('Failed to generate image. The service may be unavailable.');
        }

        if ($response->header('Content-Type') !== 'image/png') {
            Log::error('Cloudflare AI API did not return a PNG image.', [
                'headers' => $response->headers()
            ]);
            throw new Exception('The generation service returned an invalid response.');
        }

        return $response->body();
    }
}
