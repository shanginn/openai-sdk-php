<?php

declare(strict_types=1);

namespace Tests;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Shanginn\Openai\ChatCompletion\ErrorResponse;
use Shanginn\Openai\Openai;
use Shanginn\Openai\Openai\OpenaiClientInterface;
use Shanginn\Openai\Provider\Provider;
use Shanginn\Openai\Responses\Response;
use Shanginn\Openai\Responses\ResponseFunctionTool;
use Shanginn\Openai\Responses\ResponseRequest;

final class ResponsesTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testResponseRequestPreservesProviderExtensions(): void
    {
        $request = new ResponseRequest(
            model: 'qwen3.7-max',
            input: 'Hello',
            reasoning: ['effort' => 'high'],
            tools: [ResponseFunctionTool::fromTool(SampleTool::class)],
            store: false,
            extraBody: ['enable_thinking' => true],
        );

        $payload = $request->toArray();

        $this->assertSame('high', $payload['reasoning']['effort']);
        $this->assertFalse($payload['store']);
        $this->assertTrue($payload['enable_thinking']);
        $this->assertSame('function', $payload['tools'][0]['type']);
        $this->assertSame('test_tool', $payload['tools'][0]['name']);
    }

    public function testResponseParsesTypedOutputWithoutDiscardingUnknownItems(): void
    {
        $response = Response::fromJson(json_encode([
            'id' => 'resp_123',
            'model' => 'gpt-5.6-sol',
            'status' => 'completed',
            'output' => [
                ['type' => 'reasoning', 'encrypted_content' => 'opaque'],
                [
                    'type' => 'function_call',
                    'call_id' => 'call_123',
                    'name' => 'weather',
                    'arguments' => '{}',
                ],
                [
                    'type' => 'message',
                    'content' => [
                        ['type' => 'output_text', 'text' => 'Hello '],
                        ['type' => 'output_text', 'text' => 'world'],
                    ],
                ],
            ],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 4],
        ], JSON_THROW_ON_ERROR));

        $this->assertSame('Hello world', $response->outputText());
        $this->assertCount(1, $response->reasoningItems());
        $this->assertCount(1, $response->functionCalls());
        $this->assertSame('opaque', $response->reasoningItems()[0]['encrypted_content']);
    }

    public function testOpenaiSendsResponsesPayloadToCompatibleProvider(): void
    {
        $client = Mockery::mock(OpenaiClientInterface::class);
        $client
            ->shouldReceive('sendRequest')
            ->once()
            ->withArgs(function (string $path, string $json): bool {
                $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

                $this->assertSame('/responses', $path);
                $this->assertSame('grok-4.5', $payload['model']);
                $this->assertSame('Hello', $payload['input']);
                $this->assertFalse($payload['store']);

                return true;
            })
            ->andReturn(json_encode([
                'id' => 'resp_xai',
                'model' => 'grok-4.5',
                'status' => 'completed',
                'output' => [[
                    'type' => 'message',
                    'content' => [['type' => 'output_text', 'text' => 'Hi']],
                ]],
            ], JSON_THROW_ON_ERROR));

        $openai = new Openai($client, 'grok-4.5', Provider::XAI);
        $response = $openai->response(new ResponseRequest(
            model: 'grok-4.5',
            input: 'Hello',
            store: false,
        ));

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('Hi', $response->outputText());
    }

    public function testStringProviderErrorIsParsedSafely(): void
    {
        $client = Mockery::mock(OpenaiClientInterface::class);
        $client
            ->shouldReceive('sendRequest')
            ->once()
            ->andReturn('{"error":"model is unavailable","code":"model_unavailable"}');

        $response = (new Openai($client, 'grok-4.5', Provider::XAI))->response(
            new ResponseRequest(model: 'grok-4.5', input: 'Hello'),
        );

        $this->assertInstanceOf(ErrorResponse::class, $response);
        $this->assertSame('model is unavailable', $response->message);
        $this->assertSame('model_unavailable', $response->code);
    }
}
