<?php
namespace App\Services;

use App\Models\Post;

class CppSearchService
{
    private const HOST = '127.0.0.1';
    private const PORT = 9999;

    public function indexPost(Post $post): bool
    {
        $payload = json_encode([
            'id' => $post->id,
            'text' => $post->question . ' ' . $post->option_one_title . ' ' . $post->option_two_title . ' ' . $post->ai_generated_tags,
        ]);
        $command = "INDEX " . $payload;
        $response = $this->sendCommand($command);
        return $response && isset($response['status']) && $response['status'] === 'ok';
    }

    public function search(string $query): array
    {
        $payload = json_encode(['query' => $query]);
        $command = "SEARCH " . $payload;
        return $this->sendCommand($command) ?? [];
    }

    public function saveIndex(): bool
    {
        $response = $this->sendCommand("SAVE");
        return $response && isset($response['status']) && $response['status'] === 'saved';
    }

    private function sendCommand(string $command): ?array
    {
        try {
            $socket = stream_socket_client("tcp://" . self::HOST . ":" . self::PORT, $errno, $errstr, 3);
            if (!$socket) return null;

            fwrite($socket, $command);
            $response = '';
            while (!feof($socket)) {
                $response .= fread($socket, 1024);
            }
            fclose($socket);

            return json_decode($response, true);
        } catch (\Exception $e) {
            return null;
        }
    }
}
