<?php

declare(strict_types=1);

namespace Tests\Denormalizer;

use PHPUnit\Framework\TestCase;
use Shanginn\Openai\ChatCompletion\Message\Assistant\ToolCallDenormalizer;
use Shanginn\Openai\ChatCompletion\Message\Assistant\ToolCallInterface;
use Shanginn\Openai\ChatCompletion\Message\Assistant\ToolCallTypeEnum;
use Shanginn\Openai\ChatCompletion\Message\Assistant\UnknownFunctionCall;

class ToolCallDenormalizerTest extends TestCase
{
    private ToolCallDenormalizer $denormalizer;

    protected function setUp(): void
    {
        $this->denormalizer = new ToolCallDenormalizer();
    }

    public function testSupportsDenormalizationReturnsTrueForToolCallInterface(): void
    {
        $this->assertTrue(
            $this->denormalizer->supportsDenormalization([], ToolCallInterface::class)
        );
    }

    public function testSupportsDenormalizationReturnsFalseForOtherTypes(): void
    {
        $this->assertFalse(
            $this->denormalizer->supportsDenormalization([], 'stdClass')
        );
        $this->assertFalse(
            $this->denormalizer->supportsDenormalization([], UnknownFunctionCall::class)
        );
    }

    public function testGetSupportedTypes(): void
    {
        $types = $this->denormalizer->getSupportedTypes(null);
        
        $this->assertArrayHasKey(ToolCallInterface::class, $types);
        $this->assertTrue($types[ToolCallInterface::class]);
    }

    public function testDenormalizeFunctionTypeToolCall(): void
    {
        $data = [
            'id' => 'call_abc123',
            'type' => 'function',
            'function' => [
                'name' => 'test_function',
                'arguments' => '{"key": "value", "number": 42}',
            ],
        ];

        $result = $this->denormalizer->denormalize($data, ToolCallInterface::class);

        $this->assertInstanceOf(UnknownFunctionCall::class, $result);
        $this->assertEquals('call_abc123', $result->id);
        $this->assertEquals('test_function', $result->name);
        // Arguments are passed through as-is from the JSON
        $this->assertJson($result->arguments);
        $decoded = json_decode($result->arguments, true);
        $this->assertEquals('value', $decoded['key']);
        $this->assertEquals(42, $decoded['number']);
        $this->assertEquals(ToolCallTypeEnum::FUNCTION, $result->type);
    }

    public function testDenormalizeWithEmptyArguments(): void
    {
        $data = [
            'id' => 'call_empty',
            'type' => 'function',
            'function' => [
                'name' => 'no_args_function',
                'arguments' => '{}',
            ],
        ];

        $result = $this->denormalizer->denormalize($data, ToolCallInterface::class);

        $this->assertEquals('{}', $result->arguments);
    }

    public function testDenormalizeWithComplexNestedArguments(): void
    {
        $data = [
            'id' => 'call_complex',
            'type' => 'function',
            'function' => [
                'name' => 'complex_function',
                'arguments' => '{"array": [1, 2, 3], "nested": {"a": "b"}}',
            ],
        ];

        $result = $this->denormalizer->denormalize($data, ToolCallInterface::class);

        $this->assertJson($result->arguments);
        $decoded = json_decode($result->arguments, true);
        $this->assertEquals([1, 2, 3], $decoded['array']);
        $this->assertEquals(['a' => 'b'], $decoded['nested']);
    }

    public function testDenormalizeThrowsExceptionForUnknownType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown tool call type: unknown_type');

        $data = [
            'id' => 'call_unknown',
            'type' => 'unknown_type',
            'function' => [
                'name' => 'some_function',
                'arguments' => '{}',
            ],
        ];

        $this->denormalizer->denormalize($data, ToolCallInterface::class);
    }

    public function testDenormalizeThrowsExceptionForMissingType(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $data = [
            'id' => 'call_no_type',
            'function' => [
                'name' => 'some_function',
                'arguments' => '{}',
            ],
        ];

        $this->denormalizer->denormalize($data, ToolCallInterface::class);
    }

    public function testDenormalizePreservesSpecialCharactersInArguments(): void
    {
        $data = [
            'id' => 'call_special',
            'type' => 'function',
            'function' => [
                'name' => 'special_chars',
                'arguments' => '{"text": "Hello <>&\"\'"}',
            ],
        ];

        $result = $this->denormalizer->denormalize($data, ToolCallInterface::class);

        $this->assertStringContainsString('Hello', $result->arguments);
    }
}
