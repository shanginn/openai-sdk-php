<?php

declare(strict_types=1);

namespace App\Openai\Openai;

use Amp\ByteStream\BufferException;
use Amp\ByteStream\StreamException;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;

final readonly class OpenaiClient implements OpenaiClientInterface
{
    private HttpClient $client;

    public function __construct(
        private string $apiKey,
        private string $apiUrl = 'https://api.openai.com/v1',
    ) {
        $this->client = HttpClientBuilder::buildDefault();
    }

    /**
     * @param string $method
     * @param string $json
     *
     * @throws BufferException
     * @throws StreamException
     * @throws HttpException
     *
     * @return string
     */
    public function sendRequest(string $method, string $json): string
    {
        $url = "{$this->apiUrl}{$method}";

        $request = new Request($url, 'POST');
        $request->setBody($json);
        $request->setHeader('Authorization', "Bearer {$this->apiKey}");
        $request->setHeader('Content-Type', 'application/json');
        $request->setTransferTimeout(160);
        $request->setInactivityTimeout(160);

        $response = $this->client->request($request);

        return $response->getBody()->buffer();
    }
}
