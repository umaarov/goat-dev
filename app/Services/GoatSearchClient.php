<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class GoatSearchClient
{
    private string $host;
    private int $port;
    private int $timeout;

    public function __construct()
    {
        $this->host = 'goat-search';
        $this->port = 9999;
        $this->timeout = 2;
    }

    public function index(int $id, string $text): bool
    {
        $safeText = mb_substr($this->sanitize($text), 0, 7000);

        $payload = json_encode([
            'id' => $id,
            'text' => $safeText
        ]);

        if ($payload === false) {
            Log::error("GoatSearch JSON encode failed for ID: $id");
            return false;
        }

        $response = $this->send("INDEX $payload");
        return $response && isset($response['status']) && $response['status'] === 'ok';
    }

    public function search(string $query): array
    {
        $start = microtime(true);

        $payload = json_encode(['query' => $this->sanitize($query)]);
        $response = $this->send("SEARCH $payload");

        $duration = round((microtime(true) - $start) * 1000, 2);
        $count = is_array($response) ? count($response) : 0;

        if ($count > 0) {
            Log::channel('single')->info("GOAT ENGINE HIT: Found $count results for '$query' in {$duration}ms");
        } else {
            Log::channel('single')->warning("GOAT ENGINE MISS: No results for '$query' ({$duration}ms)");
        }

        return $response ?? [];
    }

    public function save(): bool
    {
        $response = $this->send("SAVE dummy_payload");
        return $response && isset($response['status']) && $response['status'] === 'saved';
    }

    private function send(string $data): ?array
    {
        $socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);

        if (!$socket) {
            Log::error("GoatSearch Connection Failed: $errstr ($errno)");
            return null;
        }

        fwrite($socket, $data);

        $responseBuffer = '';
        while (!feof($socket)) {
            $responseBuffer .= fgets($socket, 8192);
        }
        fclose($socket);

        return json_decode($responseBuffer, true);
    }

    private function sanitize(string $text): string
    {
        return str_replace(["\n", "\r"], " ", $text);
    }
}
