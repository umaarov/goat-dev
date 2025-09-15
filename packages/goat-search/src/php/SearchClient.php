<?php

namespace Umaarov\GoatSearch;

class SearchClient
{
    private string $host;
    private int $port;

    public function __construct(string $host = '127.0.0.1', int $port = 9999)
    {
        $this->host = $host;
        $this->port = $port;
    }

    public function search(string $query): array
    {
        $payload = json_encode(['query' => $query]);
        $command = "SEARCH " . $payload;

        $response = $this->sendCommand($command);
        return json_decode($response, true) ?? [];
    }

    public function index(int $id, string $text): bool
    {
        $payload = json_encode(['id' => $id, 'text' => $text]);
        $command = "INDEX " . $payload;

        $response = $this->sendCommand($command);
        return (json_decode($response, true)['status'] ?? 'error') === 'ok';
    }

    private function sendCommand(string $command): string
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false || !socket_connect($socket, $this->host, $this->port)) {
            return '{"error":"could not connect to search daemon"}';
        }

        socket_write($socket, $command, strlen($command));

        $response = '';
        while ($out = socket_read($socket, 2048)) {
            $response .= $out;
        }

        socket_close($socket);
        return $response;
    }
}
