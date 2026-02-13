<?php

declare(strict_types=1);

namespace Tests;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Shanginn\Openai\Openai;
use Shanginn\Openai\Openai\OpenaiClientInterface;
use Shanginn\Openai\ChatCompletion\CompletionRequest\ResponseFormat;
use Shanginn\Openai\ChatCompletion\CompletionRequest\ResponseFormatEnum;
use Shanginn\Openai\ChatCompletion\CompletionRequest\ToolChoice;
use Shanginn\Openai\ChatCompletion\Message\UserMessage;
use Shanginn\Openai\ChatCompletion\Message\AssistantMessage;
use Shanginn\Openai\ChatCompletion\Message\SchemedAssistantMessage;
use Shanginn\Openai\ChatCompletion\Message\Assistant\KnownFunctionCall;
use Shanginn\Openai\ChatCompletion\Message\Assistant\UnknownFunctionCall;
use Shanginn\Openai\ChatCompletion\Message\User\TextContentPart;
use Shanginn\Openai\ChatCompletion\Message\User\ImageContentPart;
use Shanginn\Openai\ChatCompletion\CompletionResponse;
use Shanginn\Openai\ChatCompletion\ErrorResponse;

 use Tests\SampleJsonSchema;
 use Tests\SampleTool;


class OpenaiCoreTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private MockInterface|OpenaiClientInterface $mockClient;
    private Openai $openai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockClient = Mockery::mock(OpenaiClientInterface::class);
        $this->openai = new Openai($this->mockClient, 'gpt-5-mini');
    }

    // ========== Basic Completion Tests ==========

    public function testCoreBasicCompletionSuccess(): void
    {
        $messages = [new UserMessage('Test prompt')];
        $expectedContent = 'Test response';

        $mockApiResponse = json_encode([
            'id' => 'chatcmpl-core-1',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-5-mini',
            'choices' => [
                [
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => $expectedContent],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => ['prompt_tokens' => 3, 'completion_tokens' => 2, 'total_tokens' => 5],
        ]);

        $this->mockClient->shouldReceive('sendRequest')->once()->andReturn($mockApiResponse);

        $response = $this->openai->completion($messages);

        $this->assertInstanceOf(CompletionResponse::class, $response);
        $this->assertCount(1, $response->choices);
        $this->assertInstanceOf(AssistantMessage::class, $response->choices[0]->message);
        $this->assertEquals($expectedContent, $response->choices[0]->message->content);
        $this->assertNull($response->choices[0]->message->toolCalls);
    }

    public function testCoreBasicCompletionReturnsApiErrorObject(): void
    {
        $mockApiErrorResponse = json_encode([
            'error' => [
                'message' => "Rate limit exceeded.",
                'type' => 'requests',
                'param' => null,
                'code' => 'rate_limit_exceeded'
            ]
        ]);

        $this->mockClient->shouldReceive('sendRequest')->once()->andReturn($mockApiErrorResponse);

        $response = $this->openai->completion([new UserMessage('Test')]);

        $this->assertInstanceOf(ErrorResponse::class, $response);
        $this->assertEquals('Rate limit exceeded.', $response->message);
        $this->assertEquals('requests', $response->type);
        $this->assertEquals('rate_limit_exceeded', $response->code);
    }

    // ========== Tool Calling Tests ==========

    public function testCoreToolCallKnownFunctionSuccess(): void
    {
        $messages = [new UserMessage('Call the test tool with param foo')];
        $expectedParam = 'foo';

        $mockApiResponse = json_encode([
            'id' => 'chatcmpl-core-tool-known',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-5-mini',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_abc123',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'test_tool', // Must match TestTool::getName()
                                    'arguments' => json_encode(['parameter' => $expectedParam]),
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
            'usage' => ['prompt_tokens' => 15, 'completion_tokens' => 10, 'total_tokens' => 25],
        ]);

        $this->mockClient->shouldReceive('sendRequest')->once()->andReturn($mockApiResponse);

        $response = $this->openai->completion(
            messages: $messages,
            tools: [SampleTool::class], // Provide the tool class
            toolChoice: ToolChoice::useTool(SampleTool::class)
        );

        $this->assertInstanceOf(CompletionResponse::class, $response);
        $this->assertCount(1, $response->choices);
        $message = $response->choices[0]->message;
        $this->assertInstanceOf(AssistantMessage::class, $message);
        $this->assertNotNull($message->toolCalls);
        $this->assertCount(1, $message->toolCalls);

        $toolCall = $message->toolCalls[0];
        $this->assertInstanceOf(KnownFunctionCall::class, $toolCall);
        $this->assertEquals('call_abc123', $toolCall->id);
        $this->assertEquals(SampleTool::class, $toolCall->tool);
        $this->assertInstanceOf(SampleTool::class, $toolCall->arguments);
        $this->assertEquals($expectedParam, $toolCall->arguments->parameter);
    }

    public function testCoreToolCallUnknownFunctionWhenArgsMismatch(): void
    {
        $messages = [new UserMessage('Call test tool with wrong args')];
        $toolName = 'test_tool';
        $rawArgs = json_encode(['wrong_arg' => 'value']); // Mismatched args

        $mockApiResponse = json_encode([
            'id' => 'chatcmpl-core-tool-unknown',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-5-mini',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_def456',
                                'type' => 'function',
                                'function' => [
                                    'name' => $toolName,
                                    'arguments' => $rawArgs, // Invalid for TestTool
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
            'usage' => ['prompt_tokens' => 15, 'completion_tokens' => 10, 'total_tokens' => 25],
        ]);

        $this->mockClient->shouldReceive('sendRequest')->once()->andReturn($mockApiResponse);

        // IMPORTANT: Provide the tool class so the SDK *tries* to deserialize
        $response = $this->openai->completion(
            messages: $messages,
        );

        $this->assertInstanceOf(CompletionResponse::class, $response);
        $this->assertCount(1, $response->choices);
        $message = $response->choices[0]->message;
        $this->assertInstanceOf(AssistantMessage::class, $message);
        $this->assertNotNull($message->toolCalls);
        $this->assertCount(1, $message->toolCalls);

        $toolCall = $message->toolCalls[0];

        // Because deserialization fails, it remains an UnknownFunctionCall
        $this->assertInstanceOf(UnknownFunctionCall::class, $toolCall);
        $this->assertEquals('call_def456', $toolCall->id);
        $this->assertEquals($toolName, $toolCall->name);
        $this->assertEquals($rawArgs, $toolCall->arguments); // Raw JSON string
    }

    public function testCoreToolCallReturnsTextWhenToolNotCalled(): void
    {
        $messages = [new UserMessage('Tell me about tools')];
        $expectedContent = "Tools are useful.";

        $mockApiResponse = json_encode([
            'id' => 'chatcmpl-core-tool-text',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-5-mini',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => $expectedContent, // Model replied with text
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => ['prompt_tokens' => 15, 'completion_tokens' => 3, 'total_tokens' => 18],
        ]);

        $this->mockClient->shouldReceive('sendRequest')->once()->andReturn($mockApiResponse);

        // We ask for a tool, but the mock response doesn't provide one
        $response = $this->openai->completion(
            messages: $messages,
            tools: [SampleTool::class],
            toolChoice: ToolChoice::useTool(SampleTool::class) // Request tool
        );

        $this->assertInstanceOf(CompletionResponse::class, $response);
        $this->assertCount(1, $response->choices);
        $message = $response->choices[0]->message;
        $this->assertInstanceOf(AssistantMessage::class, $message);
        $this->assertNull($message->toolCalls); // No tool calls in response
        $this->assertEquals($expectedContent, $message->content);
    }


    // ========== JSON Schema Tests ==========

    public function testCoreSchemaSuccess(): void
    {
        $messages = [new UserMessage('Data for schema')];
        $expectedResult = "Schema data";
        $expectedRawJson = json_encode(['result' => $expectedResult]);

        $mockApiResponse = json_encode([
            'id' => 'chatcmpl-core-schema-ok',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-5-mini',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => $expectedRawJson, // Valid JSON for the schema
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ]);

        $this->mockClient->shouldReceive('sendRequest')->once()->andReturn($mockApiResponse);

        $response = $this->openai->completion(
            messages: $messages,
            system: 'Use test_schema',
            responseFormat: new ResponseFormat(
                ResponseFormatEnum::JSON_SCHEMA,
                SampleJsonSchema::class
            )
        );

        $this->assertInstanceOf(CompletionResponse::class, $response);
        $this->assertCount(1, $response->choices);
        $message = $response->choices[0]->message;

        // Should be deserialized into SchemedAssistantMessage
        $this->assertInstanceOf(SchemedAssistantMessage::class, $message);
        $this->assertEquals($expectedRawJson, $message->content); // Raw content still present
        $this->assertInstanceOf(SampleJsonSchema::class, $message->schemedContend);
        $this->assertEquals($expectedResult, $message->schemedContend->result);
    }

    public function testCoreSchemaFailureReturnsRegularAssistantMessage(): void
    {
        $messages = [new UserMessage('Bad data for schema')];
        $invalidContent = "This is not the schema JSON.";

        $mockApiResponse = json_encode([
            'id' => 'chatcmpl-core-schema-fail',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-5-mini',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => $invalidContent, // Content doesn't match schema
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ]);

        $this->mockClient->shouldReceive('sendRequest')->once()->andReturn($mockApiResponse);

        // Request schema, but mock response provides invalid content
        $response = $this->openai->completion(
            messages: $messages,
            system: 'Use test_schema',
            responseFormat: new ResponseFormat(
                ResponseFormatEnum::JSON_SCHEMA,
                SampleJsonSchema::class
            )
        );

        $this->assertInstanceOf(CompletionResponse::class, $response);
        $this->assertCount(1, $response->choices);
        $message = $response->choices[0]->message;

        // Deserialization fails, so it remains a standard AssistantMessage
        $this->assertInstanceOf(AssistantMessage::class, $message);
        // It should NOT be an instance of SchemedAssistantMessage
        $this->assertNotInstanceOf(SchemedAssistantMessage::class, $message);
        $this->assertEquals($invalidContent, $message->content);
    }


    // ========== Image Input Test ==========

    public function testCoreImageInputSuccess(): void
    {
        $imageUrl = 'https://example.com/image.jpg';
        $promptText = 'Describe this image';
        $expectedResponse = 'An example image.';

        $messages = [
            new UserMessage([
                new TextContentPart($promptText),
                new ImageContentPart($imageUrl)
            ])
        ];

        $mockApiResponse = json_encode([
            'id' => 'chatcmpl-core-img-1',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-5-mini', // Assume vision model
            'choices' => [
                [
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => $expectedResponse],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => ['prompt_tokens' => 85, 'completion_tokens' => 5, 'total_tokens' => 90], // Example usage
        ]);

        $this->mockClient
            ->shouldReceive('sendRequest')
            ->once()
            ->withArgs(function (string $method, string $body) use ($promptText, $imageUrl) {
                $this->assertEquals('/chat/completions', $method);
                $data = json_decode($body, true);
                $this->assertIsArray($data['messages'][0]['content']);
                $this->assertEquals('text', $data['messages'][0]['content'][0]['type']);
                $this->assertEquals($promptText, $data['messages'][0]['content'][0]['text']);
                $this->assertEquals('image_url', $data['messages'][0]['content'][1]['type']);
                $this->assertEquals($imageUrl, $data['messages'][0]['content'][1]['image_url']['url']);
                // Add more checks for detail etc. if needed
                return true;
            })
            ->andReturn($mockApiResponse);

        $response = $this->openai->completion($messages);

        $this->assertInstanceOf(CompletionResponse::class, $response);
        $this->assertEquals($expectedResponse, $response->choices[0]->message->content);
    }
}