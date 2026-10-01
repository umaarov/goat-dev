<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SonarWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        if (! $this->hasValidSignature($request)) {
            Log::warning('SonarCloud webhook rejected: bad signature.', ['ip' => $request->ip()]);

            return response()->json(['error' => 'Unauthorized.'], 401);
        }

        $payload = $request->json()->all();
        $qualityGateStatus = data_get($payload, 'qualityGate.status');

        if ($qualityGateStatus !== 'ERROR') {
            return response()->json(['message' => 'Quality Gate passed. No issue created.'], 200);
        }

        $githubToken = config('services.github.api_token');

        if (! $githubToken) {
            Log::error('GITHUB_API_TOKEN is not set.');

            return response()->json(['error' => 'Server configuration error.'], 500);
        }

        $branchName = $this->clean(data_get($payload, 'branch.name', 'N/A'), 100);
        $projectKey = $this->clean(data_get($payload, 'project.key', 'N/A'), 100);
        $analysisUrl = (string) data_get($payload, 'branch.url', '');

        $host = parse_url($analysisUrl, PHP_URL_HOST);
        $analysisUrl = ($host === 'sonarcloud.io' && str_starts_with($analysisUrl, 'https://'))
            ? $analysisUrl
            : 'https://sonarcloud.io';

        // one issue per branch per hour
        if (! Cache::add('sonar_issue:'.sha1($branchName), 1, 3600)) {
            return response()->json(['message' => 'Issue already created recently.'], 200);
        }

        $issueBody = "**A SonarCloud analysis has failed the Quality Gate.**\n\n"
            ."- **Project:** {$projectKey}\n"
            ."- **Branch:** {$branchName}\n"
            ."- **Status:** {$qualityGateStatus}\n\n"
            ."[View the full analysis report on SonarCloud]({$analysisUrl})";

        $response = Http::withToken($githubToken)
            ->withHeaders(['Accept' => 'application/vnd.github+json'])
            ->timeout(15)
            ->post('https://api.github.com/repos/umaarov/goat-dev/issues', [
                'title' => "SonarCloud: Quality Gate failed on branch '{$branchName}'",
                'body' => $issueBody,
                'labels' => ['bug', 'sonarcloud'],
            ]);

        if ($response->failed()) {
            Cache::forget('sonar_issue:'.sha1($branchName));
            Log::error('Failed to create GitHub issue.', ['status' => $response->status()]);

            return response()->json(['error' => 'Failed to create GitHub issue.'], 502);
        }

        return response()->json([
            'message' => 'Successfully created GitHub issue.',
            'issue_url' => $response->json('html_url'),
        ], 201);
    }

    private function hasValidSignature(Request $request): bool
    {
        $secret = (string) config('security.sonar.webhook_secret');
        $given = (string) $request->header('X-Sonar-Webhook-HMAC-SHA256');

        if ($secret === '' || $given === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $request->getContent(), $secret), strtolower($given));
    }

    // no markdown/HTML/mentions from an external payload
    private function clean(mixed $value, int $max): string
    {
        $value = strip_tags((string) $value);
        $value = preg_replace('/[^\p{L}\p{N} ._\-\/:]/u', '', $value) ?? '';

        return Str::limit(trim($value), $max, '');
    }
}
