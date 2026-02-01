<?php

declare(strict_types=1);

namespace Tests\Denormalizer;

use PHPUnit\Framework\TestCase;
use Shanginn\Openai\ChatCompletion\CompletionRequest\Role;
use Shanginn\Openai\ChatCompletion\Message\AssistantMessage;
use Shanginn\Openai\ChatCompletion\Message\AssistantMessageDenormalizer;
use Shanginn\Openai\ChatCompletion\Message\Assistant\ToolCallTypeEnum;
use Shanginn\Openai\ChatCompletion\Message\Assistant\UnknownFunctionCall;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class AssistantMessageDenormalizerTest extends TestCase
{
    private AssistantMessageDenormalizer $denormalizer;

    protected function setUp(): void
    {
        $this->denormalizer = new AssistantMessageDenormalizer();
        
        // Create a mock denormalizer for handling tool calls
        $mockDenormalizer = $this->createMock(DenormalizerInterface::class);
        $mockDenormalizer->method('denormalize')
            ->willReturnCallback(function ($data, $type) {
                if ($type === \Shanginn\Openai\ChatCompletion\Message\Assistant\ToolCallInterface::class) {
                    return new UnknownFunctionCall(
                        id: $data['id'],
                        name: $data['function']['name'],
                        arguments: $data['function']['arguments'],
                    );
                }
                return null;
            });
        
        $this->denormalizer->setDenormalizer($mockDenormalizer);
    }

    public function testSupportsDenormalizationReturnsTrueForAssistantMessage(): void
    {
        $this->assertTrue(
            $this->denormalizer->supportsDenormalization([], AssistantMessage::class)
        );
    }

    public function testSupportsDenormalizationReturnsFalseForOtherTypes(): void
    {
        $this->assertFalse(
            $this->denormalizer->supportsDenormalization([], 'stdClass')
        );
        $this->assertFalse(
            $this->denormalizer->supportsDenormalization([], 'string')
        );
    }

    public function testGetSupportedTypes(): void
    {
        $types = $this->denormalizer->getSupportedTypes(null);
        
        $this->assertArrayHasKey(AssistantMessage::class, $types);
        $this->assertTrue($types[AssistantMessage::class]);
    }

    public function testDenormalizeWithContentOnly(): void
    {
        $data = [
            'role' => 'assistant',
            'content' => 'Hello, World!',
        ];

        $result = $this->denormalizer->denormalize($data, AssistantMessage::class);

        $this->assertInstanceOf(AssistantMessage::class, $result);
        $this->assertEquals(Role::ASSISTANT, $result->role);
        $this->assertEquals('Hello, World!', $result->content);
        $this->assertNull($result->name);
        $this->assertNull($result->refusal);
        $this->assertNull($result->toolCalls);
    }

    public function testDenormalizeWithName(): void
    {
        $data = [
            'role' => 'assistant',
            'content' => 'Test message',
            'name' => 'TestAssistant',
        ];

        $result = $this->denormalizer->denormalize($data, AssistantMessage::class);

        $this->assertEquals('TestAssistant', $result->name);
        $this->assertEquals('Test message', $result->content);
    }

    public function testDenormalizeWithRefusal(): void
    {
        $data = [
            'role' => 'assistant',
            'content' => null,
            'refusal' => 'I cannot answer that.',
        ];

        $result = $this->denormalizer->denormalize($data, AssistantMessage::class);

        $this->assertNull($result->content);
        $this->assertEquals('I cannot answer that.', $result->refusal);
    }

    public function testDenormalizeWithSingleToolCall(): void
    {
        $data = [
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => [
                [
                    'id' => 'call_123',
                    'type' => 'function',
                    'function' => [
                        'name' => 'test_function',
                        'arguments' => '{"arg": "value"}',
                    ],
                ],
            ],
        ];

        $result = $this->denormalizer->denormalize($data, AssistantMessage::class);

        $this->assertNull($result->content);
        $this->assertNotNull($result->toolCalls);
        $this->assertCount(1, $result->toolCalls);
        $this->assertInstanceOf(UnknownFunctionCall::class, $result->toolCalls[0]);
        $this->assertEquals('call_123', $result->toolCalls[0]->id);
        $this->assertEquals('test_function', $result->toolCalls[0]->name);
    }

    public function testDenormalizeWithMultipleToolCalls(): void
    {
        $data = [
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
        ];

        $result = $this->denormalizer->denormalize($data, AssistantMessage::class);

        $this->assertCount(2, $result->toolCalls);
        $this->assertEquals('call_1', $result->toolCalls[0]->id);
        $this->assertEquals('call_2', $result->toolCalls[1]->id);
    }

    public function testDenormalizeWithEmptyToolCalls(): void
    {
        $data = [
            'role' => 'assistant',
            'content' => 'No tools called',
            'tool_calls' => [],
        ];

        $result = $this->denormalizer->denormalize($data, AssistantMessage::class);

        $this->assertEquals('No tools called', $result->content);
        $this->assertIsArray($result->toolCalls);
        $this->assertCount(0, $result->toolCalls);
    }

    public function testDenormalizeWithAllFields(): void
    {
        $data = [
            'role' => 'assistant',
            'content' => 'I have a tool for you',
            'name' => 'Assistant',
            'refusal' => null,
            'tool_calls' => [
                [
                    'id' => 'call_all',
                    'type' => 'function',
                    'function' => [
                        'name' => 'all_fields_test',
                        'arguments' => '{"test": true}',
                    ],
                ],
            ],
        ];

        $result = $this->denormalizer->denormalize($data, AssistantMessage::class);

        $this->assertEquals('I have a tool for you', $result->content);
        $this->assertEquals('Assistant', $result->name);
        $this->assertNull($result->refusal);
        $this->assertCount(1, $result->toolCalls);
    }

    public function testDenormalizeWithNullContentAndNullToolCalls(): void
    {
        // This tests the bypass of constructor validation
        $data = [
            'role' => 'assistant',
            'content' => null,
        ];

        // Should not throw exception because we bypass constructor
        $result = $this->denormalizer->denormalize($data, AssistantMessage::class);

        $this->assertNull($result->content);
        $this->assertNull($result->toolCalls);
    }

    public function testDenormalizeWithUnicodeContent(): void
    {
        $data = [
            'role' => 'assistant',
            'content' => 'Unicode: ñ 中文 🎉',
        ];

        $result = $this->denormalizer->denormalize($data, AssistantMessage::class);

        $this->assertEquals('Unicode: ñ 中文 🎉', $result->content);
    }

    public function testDenormalizeWithSnakeCaseKeys(): void
    {
        // Symfony's name converter should handle this when used in the full serializer
        // But when testing the denormalizer directly, it receives already-converted data
        $data = [
            'role' => 'assistant',
            'content' => 'Test',
            'tool_calls' => [],
        ];

        $result = $this->denormalizer->denormalize($data, AssistantMessage::class);

        $this->assertEquals('Test', $result->content);
        $this->assertIsArray($result->toolCalls);
    }
}
