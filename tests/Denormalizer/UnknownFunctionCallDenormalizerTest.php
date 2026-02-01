<?php

declare(strict_types=1);

namespace Tests\Denormalizer;

use PHPUnit\Framework\TestCase;
use Shanginn\Openai\ChatCompletion\Message\Assistant\ToolCallTypeEnum;
use Shanginn\Openai\ChatCompletion\Message\Assistant\UnknownFunctionCall;
use Shanginn\Openai\ChatCompletion\Message\Assistant\UnknownFunctionCallDenormalizer;

class UnknownFunctionCallDenormalizerTest extends TestCase
{
    private UnknownFunctionCallDenormalizer $denormalizer;

    protected function setUp(): void
    {
        $this->denormalizer = new UnknownFunctionCallDenormalizer();
    }

    public function testSupportsDenormalizationReturnsTrueForUnknownFunctionCall(): void
    {
        $this->assertTrue(
            $this->denormalizer->supportsDenormalization([], UnknownFunctionCall::class)
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
        
        $this->assertArrayHasKey(UnknownFunctionCall::class, $types);
        $this->assertTrue($types[UnknownFunctionCall::class]);
    }

    public function testDenormalizeCreatesUnknownFunctionCall(): void
    {
        $data = [
            'id' => 'call_test123',
            'type' => 'function',
            'function' => [
                'name' => 'test_function',
                'arguments' => '{"param": "value"}',
            ],
        ];

        $result = $this->denormalizer->denormalize($data, UnknownFunctionCall::class);

        $this->assertInstanceOf(UnknownFunctionCall::class, $result);
        $this->assertEquals('call_test123', $result->id);
        $this->assertEquals('test_function', $result->name);
        $this->assertJson($result->arguments);
        $decoded = json_decode($result->arguments, true);
        $this->assertEquals('value', $decoded['param']);
        $this->assertEquals(ToolCallTypeEnum::FUNCTION, $result->type);
    }

    public function testDenormalizeWithEmptyArguments(): void
    {
        $data = [
            'id' => 'call_empty',
            'type' => 'function',
            'function' => [
                'name' => 'no_args',
                'arguments' => '{}',
            ],
        ];

        $result = $this->denormalizer->denormalize($data, UnknownFunctionCall::class);

        $this->assertEquals('{}', $result->arguments);
    }

    public function testDenormalizeWithComplexJsonArguments(): void
    {
        $data = [
            'id' => 'call_complex',
            'type' => 'function',
            'function' => [
                'name' => 'complex_func',
                'arguments' => '{"nested": {"deep": {"value": 123}}, "array": [1, 2, 3]}',
            ],
        ];

        $result = $this->denormalizer->denormalize($data, UnknownFunctionCall::class);

        $decodedArgs = json_decode($result->arguments, true);
        $this->assertIsArray($decodedArgs);
        $this->assertArrayHasKey('nested', $decodedArgs);
        $this->assertEquals(['deep' => ['value' => 123]], $decodedArgs['nested']);
        $this->assertEquals([1, 2, 3], $decodedArgs['array']);
    }

    public function testDenormalizeWithUnicodeArguments(): void
    {
        $data = [
            'id' => 'call_unicode',
            'type' => 'function',
            'function' => [
                'name' => 'unicode_func',
                'arguments' => '{"text": "Hello ñ 中文 🎉"}',
            ],
        ];

        $result = $this->denormalizer->denormalize($data, UnknownFunctionCall::class);

        $decodedArgs = json_decode($result->arguments, true);
        $this->assertEquals('Hello ñ 中文 🎉', $decodedArgs['text']);
    }

    public function testDenormalizeWithInvalidJsonArguments(): void
    {
        $data = [
            'id' => 'call_invalid',
            'type' => 'function',
            'function' => [
                'name' => 'invalid_func',
                'arguments' => 'not valid json {',
            ],
        ];

        $result = $this->denormalizer->denormalize($data, UnknownFunctionCall::class);

        // Should still store the raw arguments even if invalid JSON
        $this->assertEquals('not valid json {', $result->arguments);
    }
}
