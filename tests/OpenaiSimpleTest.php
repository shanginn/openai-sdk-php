<?php

declare(strict_types=1);

namespace Tests;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Shanginn\Openai\Openai;
use Shanginn\Openai\OpenaiSimple;
use Shanginn\Openai\Openai\OpenaiClientInterface;
use Shanginn\Openai\Exceptions\OpenaiErrorResponseException;
use Shanginn\Openai\Exceptions\OpenaiRefusedResponseException;
use Shanginn\Openai\Exceptions\OpenaiWrongSchemaException;
use Shanginn\Openai\Exceptions\OpenaiNoChoicesException;
use Shanginn\Openai\Exceptions\OpenaiNoContentException;

class OpenaiSimpleTest extends TestCase
{
    use MockeryPHPUnitIntegration; // Automatically closes Mockery expectations

    private MockInterface|OpenaiClientInterface $mockClient;
    private Openai $openaiCore;
    private OpenaiSimple $openaiSimple;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockClient = Mockery::mock(OpenaiClientInterface::class);
        // Use the real Openai class, but inject the mocked client
        $this->openaiCore = new Openai($this->mockClient, 'gpt-5-mini');
        $this->openaiSimple = new OpenaiSimple($this->openaiCore);
    }

    // ========== Text Generation Tests ==========

    public function testSimpleTextGenerationSuccess(): void
    {
        $systemPrompt = "Translate";
        $userPrompt = "Hello";
        $expectedResponseContent = "Bonjour";

        // Mock the response from OpenAI API
        $mockApiResponse = json_encode([
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-5-mini',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => $expectedResponseContent,
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 2, 'total_tokens' => 7],
        ]);

        $this->mockClient
            ->shouldReceive('sendRequest')
            ->once()
            // ->withArgs(function (string $method, string $body) { // Optional: Assert request body
            //     $this->assertEquals('/chat/completions', $method);
            //     $data = json_decode($body, true);
            //     $this->assertEquals('Translate', $data['messages'][0]['content']);
            //     $this->assertEquals('Hello', $data['messages'][1]['content']);
            //     return true;
            // })
            ->andReturn($mockApiResponse);

        $result = $this->openaiSimple->generate(
            system: $systemPrompt,
            userMessage: $userPrompt
        );

        $this->assertEquals($expectedResponseContent, $result);
    }

    public function testSimpleTextGenerationThrowsApiError(): void
    {
        $this->expectException(OpenaiErrorResponseException::class);
        $this->expectExceptionMessage("OpenAI API response error: Invalid API key.");

        $mockApiErrorResponse = json_encode([
            'error' => [
                'message' => "Invalid API key.",
                'type' => 'invalid_request_error',
                'param' => null,
                'code' => 'invalid_api_key'
            ]
        ]);

        $this->mockClient
            ->shouldReceive('sendRequest')
            ->once()
            ->andReturn($mockApiErrorResponse);

        $this->openaiSimple->generate(system: "Test", userMessage: "Test");
    }

    public function testSimpleTextGenerationThrowsRefusedError(): void
    {
        $this->expectException(OpenaiRefusedResponseException::class);
        $this->expectExceptionMessage("OpenAI API refused to response: I cannot answer that.");

        $mockApiResponse = json_encode([
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-5-mini',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => null, // Content might be null when refused
                        'refusal' => 'I cannot answer that.',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 0, 'total_tokens' => 5],
        ]);

        $this->mockClient
            ->shouldReceive('sendRequest')
            ->once()
            ->andReturn($mockApiResponse);

        $this->openaiSimple->generate(system: "Test", userMessage: "Tell me something forbidden");
    }

    public function testSimpleTextGenerationThrowsNoChoices(): void
    {
        $this->expectException(OpenaiNoChoicesException::class);
        $this->expectExceptionMessage('OpenAI API response has no suitable choices');

        $mockApiResponse = json_encode([
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-5-mini',
            'choices' => [], // Empty choices array
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 0, 'total_tokens' => 5],
        ]);

        $this->mockClient
            ->shouldReceive('sendRequest')
            ->once()
            ->andReturn($mockApiResponse);

        $this->openaiSimple->generate(system: "Test", userMessage: "Test");
    }

    public function testSimpleTextGenerationThrowsNoContent(): void
    {
        $this->expectException(OpenaiNoContentException::class);
        $this->expectExceptionMessage('OpenAI API response has no content');

        $mockApiResponse = json_encode([
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-5-mini',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => null, // No content
                        'refusal' => null, // And no refusal
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 0, 'total_tokens' => 5],
        ]);

        $this->mockClient
            ->shouldReceive('sendRequest')
            ->once()
            ->andReturn($mockApiResponse);

        $this->openaiSimple->generate(system: "Test", userMessage: "Test");
    }


    // ========== JSON Schema Generation Tests ==========

    public function testSimpleSchemaGenerationSuccess(): void
    {
        $systemPrompt = "Extract data using test_schema";
        $userPrompt = "The result is success";
        $expectedSchemaResult = "success";

        $mockApiResponse = json_encode([
            'id' => 'chatcmpl-456',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-5-mini',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        // IMPORTANT: The content MUST be a valid JSON string matching the schema
                        'content' => json_encode(['result' => $expectedSchemaResult]),
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ]);

        $this->mockClient
            ->shouldReceive('sendRequest')
            ->once()
            ->andReturn($mockApiResponse);

        /** @var SampleJsonSchema $result */
        $result = $this->openaiSimple->generate(
            system: $systemPrompt,
            userMessage: $userPrompt,
            schema: SampleJsonSchema::class
        );

        $this->assertInstanceOf(SampleJsonSchema::class, $result);
        $this->assertEquals($expectedSchemaResult, $result->result);
    }

    public function testSimpleSchemaGenerationThrowsWrongSchemaWhenNotJson(): void
    {
        $this->expectException(OpenaiWrongSchemaException::class);
        $this->expectExceptionMessage('OpenAI API response message is not properly schemed.');

        $mockApiResponse = json_encode([
            'id' => 'chatcmpl-789',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-5-mini',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => "This is not JSON.", // Invalid content
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ]);

        $this->mockClient
            ->shouldReceive('sendRequest')
            ->once()
            ->andReturn($mockApiResponse);

        $this->openaiSimple->generate(
            system: "Extract", userMessage: "Data", schema: SampleJsonSchema::class
        );
    }

    public function testSimpleToolCallSuccess(): void
    {
        $systemPrompt = "Call tool";
        $userPrompt = "Use parameter 'test_value'";
        $expectedToolParam = 'test_value';

        $mockApiResponse = json_encode([
            'id' => 'chatcmpl-abc',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-5-mini',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => null, // No direct content when calling tool
                        'tool_calls' => [
                            [
                                'id' => 'call_xyz',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'test_tool',
                                    // Arguments must be a valid JSON string matching the tool schema
                                    'arguments' => json_encode(['parameter' => $expectedToolParam]),
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
            'usage' => ['prompt_tokens' => 15, 'completion_tokens' => 10, 'total_tokens' => 25],
        ]);

        $this->mockClient
            ->shouldReceive('sendRequest')
            ->once()
            ->andReturn($mockApiResponse);

        /** @var SampleTool $result */
        $result = $this->openaiSimple->callTool(
            system: $systemPrompt,
            text: $userPrompt,
            tool: SampleTool::class
        );

        $this->assertInstanceOf(SampleTool::class, $result);
        $this->assertEquals($expectedToolParam, $result->parameter);
    }

    public function testSimpleToolCallThrowsWrongSchemaWhenNoToolCall(): void
    {
        $this->expectException(OpenaiWrongSchemaException::class);
        // This exception might have different messages depending on the exact failure point
        // Checking for specific content might be brittle, so checking the type is often best.

        $mockApiResponse = json_encode([
            'id' => 'chatcmpl-def',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-5-mini',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => "I didn't call the tool.", // Responded with text instead
                        'tool_calls' => [],
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => ['prompt_tokens' => 15, 'completion_tokens' => 5, 'total_tokens' => 20],
        ]);

        $this->mockClient
            ->shouldReceive('sendRequest')
            ->once()
            ->andReturn($mockApiResponse);

        $this->openaiSimple->callTool(
            system: "Call tool", text: "Do something", tool: SampleTool::class
        );
    }
}