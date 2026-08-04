<?php

declare(strict_types=1);

namespace Shanginn\Openai\Openai;

use Async\Channel;
use Async\Coroutine;
use CurlHandle;
use Shanginn\Openai\Exceptions\OpenaiTransportException;

use function Async\await;
use function Async\spawn;

final class OpenaiClient implements OpenaiClientInterface
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private string $apiKey,
        private string $apiUrl = 'https://api.openai.com/v1',
        private array $headers = [],
        private int $timeoutSeconds = 3600,
        private int $connectTimeoutSeconds = 10,
        private int $streamBufferSize = 32,
    ) {}

    public function sendRequest(string $method, string $json): string
    {
        return await($this->sendRequestAsync($method, $json));
    }

    public function sendRequestAsync(string $method, string $json): Coroutine
    {
        return spawn(fn(): string => $this->execute($method, $json));
    }

    public function streamRequest(string $method, string $json): iterable
    {
        $channel = new Channel($this->streamBufferSize);

        $producer = spawn(function () use ($channel, $method, $json): void {
            try {
                $this->executeStream($method, $json, $channel);
            } finally {
                $channel->close();
            }
        });

        try {
            foreach ($channel as $data) {
                yield $data;
            }

            await($producer);
        } finally {
            if (!$producer->isCompleted()) {
                $producer->cancel();
            }
        }
    }

    private function execute(string $method, string $json): string
    {
        $handle = $this->createHandle($method, $json);

        try {
            $response = curl_exec($handle);

            if ($response === false) {
                throw new OpenaiTransportException(
                    sprintf('OpenAI-compatible HTTP request failed: %s', curl_error($handle)),
                );
            }

            return $response;
        } finally {
            curl_close($handle);
        }
    }

    private function executeStream(string $method, string $json, Channel $channel): void
    {
        $buffer = '';
        $handle = $this->createHandle($method, $json, streaming: true);

        curl_setopt(
            $handle,
            CURLOPT_WRITEFUNCTION,
            function (CurlHandle $handle, string $chunk) use (&$buffer, $channel): int {
                $buffer .= $chunk;

                while (preg_match(
                    '/\r\n\r\n|\n\n|\r\r/',
                    $buffer,
                    $boundaryMatch,
                    PREG_OFFSET_CAPTURE,
                ) === 1) {
                    $boundary = $boundaryMatch[0][1];
                    $separatorLength = strlen($boundaryMatch[0][0]);
                    $event = substr($buffer, 0, $boundary);
                    $buffer = substr($buffer, $boundary + $separatorLength);
                    $this->emitServerSentEvent($event, $channel);
                }

                return strlen($chunk);
            },
        );

        try {
            $result = curl_exec($handle);

            if ($result === false) {
                throw new OpenaiTransportException(
                    sprintf('OpenAI-compatible streaming request failed: %s', curl_error($handle)),
                );
            }

            if (trim($buffer) !== '') {
                $this->emitServerSentEvent($buffer, $channel);
            }
        } finally {
            curl_close($handle);
        }
    }

    private function createHandle(string $method, string $json, bool $streaming = false): CurlHandle
    {
        $handle = curl_init($this->buildUrl($method));

        if (!$handle instanceof CurlHandle) {
            throw new OpenaiTransportException('Unable to initialize cURL.');
        }

        $headers = [
            'Accept' => $streaming ? 'text/event-stream' : 'application/json',
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
            ...$this->headers,
        ];

        curl_setopt_array($handle, [
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeoutSeconds,
            CURLOPT_HTTPHEADER => array_map(
                static fn(string $name, string $value): string => "{$name}: {$value}",
                array_keys($headers),
                array_values($headers),
            ),
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
        ]);

        return $handle;
    }

    private function buildUrl(string $method): string
    {
        if (filter_var($method, FILTER_VALIDATE_URL) !== false) {
            return $method;
        }

        return rtrim($this->apiUrl, '/') . '/' . ltrim($method, '/');
    }

    private function emitServerSentEvent(string $event, Channel $channel): void
    {
        $data = [];

        foreach (preg_split('/\r\n|\n|\r/', $event) ?: [] as $line) {
            if (str_starts_with($line, 'data:')) {
                $data[] = ltrim(substr($line, 5));
            }
        }

        if ($data === []) {
            $trimmed = trim($event);
            if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
                $channel->send($trimmed);
            }

            return;
        }

        $payload = implode("\n", $data);
        if ($payload !== '[DONE]') {
            $channel->send($payload);
        }
    }
}
