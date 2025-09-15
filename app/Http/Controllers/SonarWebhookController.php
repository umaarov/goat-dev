<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SonarWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->all();

        $qualityGateStatus = data_get($payload, 'qualityGate.status');

        Log::info('SonarCloud webhook received.', ['status' => $qualityGateStatus]);

        if ($qualityGateStatus !== 'ERROR') {
            return response()->json(['message' => 'Quality Gate passed. No issue created.'], 200);
        }

        $githubToken = env('GITHUB_API_TOKEN');
        $githubRepoOwner = 'umaarov';
        $githubRepoName = 'goat-dev';

        if (!$githubToken) {
            Log::error('GITHUB_API_TOKEN is not set in the .env file.');
            return response()->json(['error' => 'Server configuration error.'], 500);
        }

        $branchName = data_get($payload, 'branch.name', 'N/A');
        $analysisUrl = data_get($payload, 'branch.url', '#');
        $projectKey = data_get($payload, 'project.key', 'N/A');

        $issueTitle = "SonarCloud: Quality Gate failed on branch '{$branchName}'";

        $issueBody = "**🚨 A SonarCloud analysis has failed the Quality Gate.**\n\n"
            . "- **Project:** {$projectKey}\n"
            . "- **Branch:** {$branchName}\n"
            . "- **Status:** {$qualityGateStatus}\n\n"
            . "**[View the full analysis report on SonarCloud]({$analysisUrl})**";

        $response = Http::withToken($githubToken)
            ->withHeaders(['Accept' => 'application/vnd.github.v3+json'])
            ->post("https://api.github.com/repos/{$githubRepoOwner}/{$githubRepoName}/issues", [
                'title' => $issueTitle,
                'body' => $issueBody,
                'labels' => ['bug', 'sonarcloud'],
            ]);

        if ($response->failed()) {
            Log::error('Failed to create GitHub issue.', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return response()->json(['error' => 'Failed to create GitHub issue.'], 500);
        }

        $createdIssue = $response->json();
        Log::info('Successfully created GitHub issue.', ['issue_number' => $createdIssue['number']]);

        return response()->json([
            'message' => 'Successfully created GitHub issue.',
            'issue_url' => $createdIssue['html_url']
        ], 201);
    }
}
