<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Shanginn\Openai\ChatCompletion\CompletionResponse;
use Shanginn\Openai\ChatCompletion\CompletionResponse\Choice;
use Shanginn\Openai\ChatCompletion\CompletionResponse\Usage;
use Shanginn\Openai\ChatCompletion\ErrorResponse;
use Shanginn\Openai\ChatCompletion\Message\AssistantMessage;
use Shanginn\Openai\ChatCompletion\Message\Assistant\KnownFunctionCall;
use Shanginn\Openai\ChatCompletion\Message\Assistant\ToolCallInterface;
use Shanginn\Openai\ChatCompletion\Message\Assistant\UnknownFunctionCall;
use Shanginn\Openai\ChatCompletion\Message\User\ImageContentPart;
use Shanginn\Openai\ChatCompletion\Message\User\ImageDetailLevelEnum;
use Shanginn\Openai\ChatCompletion\Message\User\TextContentPart;
use Shanginn\Openai\Exceptions\OpenaiDeserializeException;
use Shanginn\Openai\Openai\OpenaiSerializer;

class OpenaiSerializerTest extends TestCase
{
    private OpenaiSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new OpenaiSerializer();
    }

    // ========== Basic Serialization Tests ==========

    public function testSerializeBasicCompletionResponse(): void
    {
        $response = new CompletionResponse(
            id: 'test-id',
            choices: [
                new Choice(
                    index: 0,
                    message: new AssistantMessage(content: 'Hello'),
                    finishReason: 'stop',
                ),
            ],
            model: 'gpt-5-mini',
            usage: new Usage(completionTokens: 5, promptTokens: 10, totalTokens: 15),
            object: 'chat.completion',
            created: 1234567890,
        );

        $json = $this->serializer->serialize($response);
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertEquals('test-id', $decoded['id']);
        $this->assertEquals('chat.completion', $decoded['object']);
        $this->assertEquals(1234567890, $decoded['created']);
        $this->assertEquals('gpt-5-mini', $decoded['model']);
        $this->assertArrayHasKey('choices', $decoded);
        $this->assertCount(1, $decoded['choices']);
        $this->assertEquals('stop', $decoded['choices'][0]['finish_reason']);
        $this->assertEquals('Hello', $decoded['choices'][0]['message']['content']);
        $this->assertEquals('assistant', $decoded['choices'][0]['message']['role']);
        $this->assertArrayHasKey('usage', $decoded);
        $this->assertEquals(5, $decoded['usage']['completion_tokens']);
        $this->assertEquals(10, $decoded['usage']['prompt_tokens']);
        $this->assertEquals(15, $decoded['usage']['total_tokens']);
    }

    public function testSerializeSkipsNullValues(): void
    {
        $response = new CompletionResponse(
            id: 'test-id',
            choices: [
                new Choice(
                    index: 0,
                    message: new AssistantMessage(content: 'Test'),
                    finishReason: 'stop',
                    logprobs: null,
                ),
            ],
            model: 'gpt-5-mini',
            usage: new Usage(completionTokens: 1, promptTokens: 1, totalTokens: 2),
            object: 'chat.completion',
            created: 1234567890,
            serviceTier: null,
            systemFingerprint: null,
        );

        $json = $this->serializer->serialize($response);
        $decoded = json_decode($json, true);

        // These null properties should be absent due to SKIP_NULL_VALUES
        $this->assertArrayNotHasKey('service_tier', $decoded);
        $this->assertArrayNotHasKey('system_fingerprint', $decoded);
        $this->assertArrayNotHasKey('logprobs', $decoded['choices'][0]);
    }

    // ========== Basic Deserialization Tests ==========

    public function testDeserializeBasicCompletionResponse(): void
    {
        $json = json_encode([
            'id' => 'test-id',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'model' => 'gpt-5-mini',
            'choices' => [
                [
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => 'Hello World'],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 5,
                'total_tokens' => 15,
            ],
        ]);

        $result = $this->serializer->deserialize($json, CompletionResponse::class);

        $this->assertInstanceOf(CompletionResponse::class, $result);
        $this->assertEquals('test-id', $result->id);
        $this->assertEquals('gpt-5-mini', $result->model);
        $this->assertCount(1, $result->choices);
        $this->assertInstanceOf(Choice::class, $result->choices[0]);
        $this->assertInstanceOf(AssistantMessage::class, $result->choices[0]->message);
        $this->assertEquals('Hello World', $result->choices[0]->message->content);
        $this->assertEquals('stop', $result->choices[0]->finishReason);
        $this->assertInstanceOf(Usage::class, $result->usage);
        $this->assertEquals(10, $result->usage->promptTokens);
        $this->assertEquals(5, $result->usage->completionTokens);
        $this->assertEquals(15, $result->usage->totalTokens);
    }

    public function testDeserializeMultipleChoices(): void
    {
        $json = json_encode([
            'id' => 'test-multi',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'model' => 'gpt-5-mini',
            'choices' => [
                [
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => 'First choice'],
                    'finish_reason' => 'stop',
                ],
                [
                    'index' => 1,
                    'message' => ['role' => 'assistant', 'content' => 'Second choice'],
                    'finish_reason' => 'length',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 10,
                'total_tokens' => 20,
            ],
        ]);

        $result = $this->serializer->deserialize($json, CompletionResponse::class);

        $this->assertCount(2, $result->choices);
        $this->assertEquals(0, $result->choices[0]->index);
        $this->assertEquals('First choice', $result->choices[0]->message->content);
        $this->assertEquals('stop', $result->choices[0]->finishReason);
        $this->assertEquals(1, $result->choices[1]->index);
        $this->assertEquals('Second choice', $result->choices[1]->message->content);
        $this->assertEquals('length', $result->choices[1]->finishReason);
    }

    public function testDeserializeErrorResponse(): void
    {
        // ErrorResponse doesn't have snake_case properties, it uses the error wrapper
        $json = json_encode([
            'message' => 'Invalid API key',
            'type' => 'invalid_request_error',
            'param' => null,
            'code' => 'invalid_api_key',
        ]);

        $result = $this->serializer->deserialize($json, ErrorResponse::class);

        $this->assertInstanceOf(ErrorResponse::class, $result);
        $this->assertEquals('Invalid API key', $result->message);
        $this->assertEquals('invalid_request_error', $result->type);
        $this->assertNull($result->param);
        $this->assertEquals('invalid_api_key', $result->code);
    }

    // ========== Tool Call Deserialization Tests ==========

    public function testDeserializeCompletionWithToolCalls(): void
    {
        $json = json_encode([
            'id' => 'test-tool',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'model' => 'gpt-5-mini',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_123',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'test_function',
                                    'arguments' => '{"key": "value"}',
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 15,
                'completion_tokens' => 10,
                'total_tokens' => 25,
            ],
        ]);

        $result = $this->serializer->deserialize($json, CompletionResponse::class);

        $this->assertInstanceOf(CompletionResponse::class, $result);
        $message = $result->choices[0]->message;
        $this->assertNull($message->content);
        $this->assertNotNull($message->toolCalls);
        $this->assertCount(1, $message->toolCalls);
        $this->assertInstanceOf(ToolCallInterface::class, $message->toolCalls[0]);
        $this->assertInstanceOf(UnknownFunctionCall::class, $message->toolCalls[0]);
        
        $toolCall = $message->toolCalls[0];
        $this->assertEquals('call_123', $toolCall->id);
        $this->assertEquals('test_function', $toolCall->name);
        $this->assertEquals('{"key": "value"}', $toolCall->arguments);
    }

    public function testDeserializeCompletionWithMultipleToolCalls(): void
    {
        $json = json_encode([
            'id' => 'test-multi-tools',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'model' => 'gpt-5-mini',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_1',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'func_one',
                                    'arguments' => '{}',
                                ],
                            ],
                            [
                                'id' => 'call_2',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'func_two',
                                    'arguments' => '{}',
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 20,
                'completion_tokens' => 15,
                'total_tokens' => 35,
            ],
        ]);

        $result = $this->serializer->deserialize($json, CompletionResponse::class);

        $this->assertCount(2, $result->choices[0]->message->toolCalls);
        
        $toolCall1 = $result->choices[0]->message->toolCalls[0];
        $this->assertEquals('call_1', $toolCall1->id);
        $this->assertEquals('func_one', $toolCall1->name);
        
        $toolCall2 = $result->choices[0]->message->toolCalls[1];
        $this->assertEquals('call_2', $toolCall2->id);
        $this->assertEquals('func_two', $toolCall2->name);
    }

    // ========== Refusal Handling Tests ==========

    public function testDeserializeCompletionWithRefusal(): void
    {
        $json = json_encode([
            'id' => 'test-refusal',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'model' => 'gpt-5-mini',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'refusal' => 'I cannot answer that question.',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 5,
                'completion_tokens' => 0,
                'total_tokens' => 5,
            ],
        ]);

        $result = $this->serializer->deserialize($json, CompletionResponse::class);

        $this->assertNull($result->choices[0]->message->content);
        $this->assertEquals('I cannot answer that question.', $result->choices[0]->message->refusal);
    }

    // ========== User Message Content Parts Tests ==========

    public function testSerializeTextContentPart(): void
    {
        $contentPart = new TextContentPart('Hello, this is a test message');
        
        $json = $this->serializer->serialize($contentPart);
        $decoded = json_decode($json, true);

        $this->assertEquals('text', $decoded['type']);
        $this->assertEquals('Hello, this is a test message', $decoded['text']);
    }

    public function testSerializeImageContentPart(): void
    {
        $contentPart = new ImageContentPart(
            url: 'https://example.com/image.jpg',
            detail: ImageDetailLevelEnum::HIGH,
        );
        
        $json = $this->serializer->serialize($contentPart);
        $decoded = json_decode($json, true);

        $this->assertEquals('image_url', $decoded['type']);
        $this->assertEquals('https://example.com/image.jpg', $decoded['image_url']['url']);
        $this->assertEquals('high', $decoded['image_url']['detail']);
    }

    public function testSerializeImageContentPartWithoutDetail(): void
    {
        $contentPart = new ImageContentPart(url: 'https://example.com/image.jpg');
        
        $json = $this->serializer->serialize($contentPart);
        $decoded = json_decode($json, true);

        $this->assertEquals('image_url', $decoded['type']);
        $this->assertEquals('https://example.com/image.jpg', $decoded['image_url']['url']);
        $this->assertArrayNotHasKey('detail', $decoded['image_url']);
    }

    // ========== Round-trip Tests ==========

    public function testRoundTripCompletionResponse(): void
    {
        $original = new CompletionResponse(
            id: 'round-trip-id',
            choices: [
                new Choice(
                    index: 0,
                    message: new AssistantMessage(content: 'Round trip test'),
                    finishReason: 'stop',
                ),
            ],
            model: 'gpt-5-mini',
            usage: new Usage(completionTokens: 3, promptTokens: 5, totalTokens: 8),
            object: 'chat.completion',
            created: 1234567890,
        );

        $json = $this->serializer->serialize($original);
        $deserialized = $this->serializer->deserialize($json, CompletionResponse::class);

        $this->assertEquals($original->id, $deserialized->id);
        $this->assertEquals($original->model, $deserialized->model);
        $this->assertEquals($original->object, $deserialized->object);
        $this->assertEquals($original->created, $deserialized->created);
        $this->assertCount(count($original->choices), $deserialized->choices);
        $this->assertEquals($original->choices[0]->index, $deserialized->choices[0]->index);
        $this->assertEquals($original->choices[0]->message->content, $deserialized->choices[0]->message->content);
        $this->assertEquals($original->choices[0]->finishReason, $deserialized->choices[0]->finishReason);
        $this->assertEquals($original->usage->promptTokens, $deserialized->usage->promptTokens);
        $this->assertEquals($original->usage->completionTokens, $deserialized->usage->completionTokens);
        $this->assertEquals($original->usage->totalTokens, $deserialized->usage->totalTokens);
    }

    public function testRoundTripWithToolCalls(): void
    {
        // Use withToolCalls to properly create message with tools
        $baseMessage = new AssistantMessage(content: 'Using tool');
        $messageWithTools = AssistantMessage::withToolCalls(
            $baseMessage,
            [
                new KnownFunctionCall(
                    id: 'call_rt',
                    tool: SampleTool::class,
                    arguments: new SampleTool(parameter: 'test_value'),
                ),
            ]
        );
        
        $original = new CompletionResponse(
            id: 'tool-round-trip',
            choices: [
                new Choice(
                    index: 0,
                    message: $messageWithTools,
                    finishReason: 'tool_calls',
                ),
            ],
            model: 'gpt-5-mini',
            usage: new Usage(completionTokens: 10, promptTokens: 15, totalTokens: 25),
            object: 'chat.completion',
            created: 1234567890,
        );

        $json = $this->serializer->serialize($original);
        $decoded = json_decode($json, true);
        
        // Verify serialization structure
        $this->assertEquals('tool_calls', $decoded['choices'][0]['finish_reason']);
        $this->assertEquals('Using tool', $decoded['choices'][0]['message']['content']);
        $this->assertCount(1, $decoded['choices'][0]['message']['tool_calls']);
        $this->assertEquals('call_rt', $decoded['choices'][0]['message']['tool_calls'][0]['id']);
        $this->assertEquals('test_tool', $decoded['choices'][0]['message']['tool_calls'][0]['function']['name']);
    }

    // ========== Error Handling Tests ==========

    public function testDeserializeInvalidJsonThrowsException(): void
    {
        $this->expectException(OpenaiDeserializeException::class);
        
        $this->serializer->deserialize('not valid json', CompletionResponse::class);
    }

    public function testDeserializeEmptyObjectThrowsException(): void
    {
        $this->expectException(OpenaiDeserializeException::class);
        
        $this->serializer->deserialize('{}', CompletionResponse::class);
    }

    public function testDeserializeMissingRequiredFieldsThrowsException(): void
    {
        $this->expectException(OpenaiDeserializeException::class);
        
        $json = json_encode(['id' => 'test']);
        $this->serializer->deserialize($json, CompletionResponse::class);
    }

    // ========== Edge Cases ==========

    public function testDeserializeWithEmptyContent(): void
    {
        $json = json_encode([
            'id' => 'empty-content',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'model' => 'gpt-5-mini',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => '',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 1,
                'total_tokens' => 11,
            ],
        ]);

        $result = $this->serializer->deserialize($json, CompletionResponse::class);

        $this->assertEquals('', $result->choices[0]->message->content);
    }

    public function testDeserializeWithSpecialCharactersInContent(): void
    {
        $json = json_encode([
            'id' => 'special-chars',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'model' => 'gpt-5-mini',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Hello <>&"\' 🎉 Unicode: ñ 中文',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 5,
                'total_tokens' => 15,
            ],
        ]);

        $result = $this->serializer->deserialize($json, CompletionResponse::class);

        $this->assertEquals('Hello <>&"\' 🎉 Unicode: ñ 中文', $result->choices[0]->message->content);
    }

    public function testDeserializeWithIntegerCodeInError(): void
    {
        $json = json_encode([
            'message' => 'Rate limit exceeded',
            'type' => 'rate_limit',
            'param' => null,
            'code' => 429,
        ]);

        $result = $this->serializer->deserialize($json, ErrorResponse::class);

        $this->assertEquals(429, $result->code);
    }

    public function testDeserializeWithNestedLogprobs(): void
    {
        $json = json_encode([
            'id' => 'logprobs-test',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'model' => 'gpt-5-mini',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Test',
                    ],
                    'finish_reason' => 'stop',
                    'logprobs' => [
                        'content' => [
                            ['token' => 'Test', 'logprob' => -0.5, 'bytes' => [84, 101, 115, 116]],
                        ],
                    ],
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 1,
                'total_tokens' => 11,
            ],
        ]);

        $result = $this->serializer->deserialize($json, CompletionResponse::class);

        $this->assertIsArray($result->choices[0]->logprobs);
        $this->assertArrayHasKey('content', $result->choices[0]->logprobs);
    }
}
