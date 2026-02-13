<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Shanginn\Openai\ChatCompletion\CompletionResponse;
use Shanginn\Openai\ChatCompletion\CompletionResponse\Choice;
use Shanginn\Openai\ChatCompletion\CompletionResponse\Usage;
use Shanginn\Openai\ChatCompletion\Message\Assistant\KnownFunctionCall;
use Shanginn\Openai\ChatCompletion\Message\Assistant\UnknownFunctionCall;
use Shanginn\Openai\ChatCompletion\Message\AssistantMessage;
use Shanginn\Openai\ChatCompletion\Message\MessageInterface;
use Shanginn\Openai\ChatCompletion\Message\SystemMessage;
use Shanginn\Openai\ChatCompletion\Message\ToolMessage;
use Shanginn\Openai\ChatCompletion\Message\UserMessage;
use Shanginn\Openai\Openai\OpenaiSerializer;

class OpenaiSerializerTest extends TestCase
{
    private OpenaiSerializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serializer = new OpenaiSerializer();
    }

    public function testDeserializeCompletionResponseConvertsKnownTools(): void
    {
        $toolId = 'call_abc123';
        $toolName = 'test_tool';
        $expectedParam = 'foo';

        $responseJson = json_encode([
            'id' => 'chatcmpl-test',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-4',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => $toolId,
                                'type' => 'function',
                                'function' => [
                                    'name' => $toolName,
                                    'arguments' => json_encode(['parameter' => $expectedParam]),
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ]);

        $tools = [SampleTool::class];

        /** @var CompletionResponse $result */
        $result = $this->serializer->deserialize($responseJson, CompletionResponse::class, $tools);

        $this->assertInstanceOf(CompletionResponse::class, $result);
        $this->assertCount(1, $result->choices);
        
        $message = $result->choices[0]->message;
        $this->assertInstanceOf(AssistantMessage::class, $message);
        $this->assertNotNull($message->toolCalls);
        $this->assertCount(1, $message->toolCalls);
        
        $toolCall = $message->toolCalls[0];
        $this->assertInstanceOf(KnownFunctionCall::class, $toolCall);
        $this->assertEquals($toolId, $toolCall->id);
        $this->assertEquals(SampleTool::class, $toolCall->tool);
        $this->assertInstanceOf(SampleTool::class, $toolCall->arguments);
        $this->assertEquals($expectedParam, $toolCall->arguments->parameter);
    }

    public function testDeserializeCompletionResponsePreservesUnknownTools(): void
    {
        $toolId = 'call_def456';
        $toolName = 'unknown_tool';
        $rawArgs = json_encode(['some_param' => 'value']);

        $responseJson = json_encode([
            'id' => 'chatcmpl-test',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-4',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => $toolId,
                                'type' => 'function',
                                'function' => [
                                    'name' => $toolName,
                                    'arguments' => $rawArgs,
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ]);

        // Empty tools - tool not registered
        $tools = [];

        /** @var CompletionResponse $result */
        $result = $this->serializer->deserialize($responseJson, CompletionResponse::class, $tools);

        $this->assertInstanceOf(CompletionResponse::class, $result);
        $message = $result->choices[0]->message;
        $this->assertNotNull($message->toolCalls);
        
        // Should remain UnknownFunctionCall since tool is not in the map
        $toolCall = $message->toolCalls[0];
        $this->assertInstanceOf(UnknownFunctionCall::class, $toolCall);
        $this->assertEquals($toolId, $toolCall->id);
        $this->assertEquals($toolName, $toolCall->name);
        $this->assertEquals($rawArgs, $toolCall->arguments);
    }

    public function testDeserializeCompletionResponsePreservesUnknownToolsOnInvalidJson(): void
    {
        $toolId = 'call_ghi789';
        $toolName = 'test_tool';
        $rawArgs = 'not valid json';

        $responseJson = json_encode([
            'id' => 'chatcmpl-test',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-4',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => $toolId,
                                'type' => 'function',
                                'function' => [
                                    'name' => $toolName,
                                    'arguments' => $rawArgs,
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ]);

        $tools = [SampleTool::class];

        /** @var CompletionResponse $result */
        $result = $this->serializer->deserialize($responseJson, CompletionResponse::class, $tools);

        $this->assertInstanceOf(CompletionResponse::class, $result);
        $message = $result->choices[0]->message;
        $this->assertNotNull($message->toolCalls);
        
        // Should remain UnknownFunctionCall since deserialization fails
        $toolCall = $message->toolCalls[0];
        $this->assertInstanceOf(UnknownFunctionCall::class, $toolCall);
        $this->assertEquals($toolId, $toolCall->id);
        $this->assertEquals($toolName, $toolCall->name);
        $this->assertEquals($rawArgs, $toolCall->arguments);
    }

    public function testDeserializeCompletionResponseWithMultipleToolCalls(): void
    {
        $knownToolId = 'call_known';
        $unknownToolId = 'call_unknown';
        $knownToolName = 'test_tool';
        $unknownToolName = 'unknown_tool';

        $responseJson = json_encode([
            'id' => 'chatcmpl-test',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-4',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => $knownToolId,
                                'type' => 'function',
                                'function' => [
                                    'name' => $knownToolName,
                                    'arguments' => json_encode(['parameter' => 'foo']),
                                ],
                            ],
                            [
                                'id' => $unknownToolId,
                                'type' => 'function',
                                'function' => [
                                    'name' => $unknownToolName,
                                    'arguments' => json_encode(['param' => 'bar']),
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ]);

        $tools = [SampleTool::class];

        /** @var CompletionResponse $result */
        $result = $this->serializer->deserialize($responseJson, CompletionResponse::class, $tools);

        $this->assertInstanceOf(CompletionResponse::class, $result);
        $message = $result->choices[0]->message;
        $this->assertNotNull($message->toolCalls);
        $this->assertCount(2, $message->toolCalls);
        
        // First should be converted to KnownFunctionCall
        $this->assertInstanceOf(KnownFunctionCall::class, $message->toolCalls[0]);
        $this->assertEquals($knownToolId, $message->toolCalls[0]->id);
        
        // Second should remain UnknownFunctionCall
        $this->assertInstanceOf(UnknownFunctionCall::class, $message->toolCalls[1]);
        $this->assertEquals($unknownToolId, $message->toolCalls[1]->id);
    }

    public function testDeserializeCompletionResponseWithoutToolCalls(): void
    {
        $responseJson = json_encode([
            'id' => 'chatcmpl-test',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-4',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Hello!',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ]);

        $tools = [SampleTool::class];

        /** @var CompletionResponse $result */
        $result = $this->serializer->deserialize($responseJson, CompletionResponse::class, $tools);

        $this->assertInstanceOf(CompletionResponse::class, $result);
        $this->assertCount(1, $result->choices);
        $this->assertEquals('Hello!', $result->choices[0]->message->content);
        $this->assertNull($result->choices[0]->message->toolCalls);
    }

    public function testDeserializeCompletionResponseWithoutToolsMap(): void
    {
        $toolId = 'call_abc123';
        $toolName = 'test_tool';

        $responseJson = json_encode([
            'id' => 'chatcmpl-test',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-4',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => $toolId,
                                'type' => 'function',
                                'function' => [
                                    'name' => $toolName,
                                    'arguments' => json_encode(['parameter' => 'foo']),
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ]);

        // No tools map provided - should not convert
        /** @var CompletionResponse $result */
        $result = $this->serializer->deserialize($responseJson, CompletionResponse::class);

        $this->assertInstanceOf(CompletionResponse::class, $result);
        $message = $result->choices[0]->message;
        $this->assertNotNull($message->toolCalls);
        
        // Should remain UnknownFunctionCall
        $toolCall = $message->toolCalls[0];
        $this->assertInstanceOf(UnknownFunctionCall::class, $toolCall);
    }

    public function testDeserializeArrayOfMessages(): void
    {
        $messagesJson = json_encode([
            ['role' => 'user', 'content' => 'Hello!'],
            ['role' => 'assistant', 'content' => 'Hi there!'],
            ['role' => 'system', 'content' => 'You are helpful.'],
            ['role' => 'tool', 'content' => 'Tool result', 'tool_call_id' => 'call_123'],
        ]);

        /** @var array<MessageInterface> $result */
        $result = $this->serializer->deserialize($messagesJson, 'array');

        $this->assertIsArray($result);
        $this->assertCount(4, $result);

        $this->assertInstanceOf(UserMessage::class, $result[0]);
        $this->assertEquals('user', $result[0]->role->value ?? $result[0]->role);
        $this->assertEquals('Hello!', $result[0]->content);

        $this->assertInstanceOf(AssistantMessage::class, $result[1]);
        $this->assertEquals('assistant', $result[1]->role->value ?? $result[1]->role);
        $this->assertEquals('Hi there!', $result[1]->content);

        $this->assertInstanceOf(SystemMessage::class, $result[2]);
        $this->assertEquals('system', $result[2]->role->value ?? $result[2]->role);
        $this->assertEquals('You are helpful.', $result[2]->content);

        $this->assertInstanceOf(ToolMessage::class, $result[3]);
        $this->assertEquals('tool', $result[3]->role->value ?? $result[3]->role);
        $this->assertEquals('Tool result', $result[3]->content);
        $this->assertEquals('call_123', $result[3]->toolCallId);
    }

    public function testDeserializeEmptyArray(): void
    {
        $result = $this->serializer->deserialize('[]', 'array');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testDeserializeArrayWithInvalidRoleThrowsException(): void
    {
        $messagesJson = json_encode([
            ['role' => 'user', 'content' => 'Hello!'],
            ['role' => 'unknown_role', 'content' => 'Invalid'],
            ['role' => 'assistant', 'content' => 'Hi!'],
        ]);

        // Unknown roles should throw an exception - this is the expected behavior
        $this->expectException(\Shanginn\Openai\Exceptions\OpenaiDeserializeException::class);

        $this->serializer->deserialize($messagesJson, 'array');
    }
}
