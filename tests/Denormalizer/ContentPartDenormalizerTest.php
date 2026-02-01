<?php

declare(strict_types=1);

namespace Tests\Denormalizer;

use PHPUnit\Framework\TestCase;
use Shanginn\Openai\ChatCompletion\Message\User\ContentPartDenormalizer;
use Shanginn\Openai\ChatCompletion\Message\User\ContentPartInterface;
use Shanginn\Openai\ChatCompletion\Message\User\ContentPartTypeEnum;
use Shanginn\Openai\ChatCompletion\Message\User\ImageContentPart;
use Shanginn\Openai\ChatCompletion\Message\User\ImageDetailLevelEnum;
use Shanginn\Openai\ChatCompletion\Message\User\TextContentPart;

class ContentPartDenormalizerTest extends TestCase
{
    private ContentPartDenormalizer $denormalizer;

    protected function setUp(): void
    {
        $this->denormalizer = new ContentPartDenormalizer();
    }

    public function testSupportsDenormalizationReturnsTrueForContentPartInterface(): void
    {
        $this->assertTrue(
            $this->denormalizer->supportsDenormalization([], ContentPartInterface::class)
        );
    }

    public function testSupportsDenormalizationReturnsFalseForOtherTypes(): void
    {
        $this->assertFalse(
            $this->denormalizer->supportsDenormalization([], 'stdClass')
        );
        $this->assertFalse(
            $this->denormalizer->supportsDenormalization([], TextContentPart::class)
        );
    }

    public function testGetSupportedTypes(): void
    {
        $types = $this->denormalizer->getSupportedTypes(null);
        
        $this->assertArrayHasKey(ContentPartInterface::class, $types);
        $this->assertTrue($types[ContentPartInterface::class]);
    }

    public function testDenormalizeTextType(): void
    {
        $data = [
            'type' => 'text',
            'text' => 'This is a test message',
        ];

        $result = $this->denormalizer->denormalize($data, ContentPartInterface::class);

        $this->assertInstanceOf(TextContentPart::class, $result);
        $this->assertEquals('This is a test message', $result->text);
        $this->assertEquals(ContentPartTypeEnum::TEXT, $result->type);
    }

    public function testDenormalizeTextTypeWithEmptyString(): void
    {
        $data = [
            'type' => 'text',
            'text' => '',
        ];

        $result = $this->denormalizer->denormalize($data, ContentPartInterface::class);

        $this->assertInstanceOf(TextContentPart::class, $result);
        $this->assertEquals('', $result->text);
    }

    public function testDenormalizeTextTypeWithUnicode(): void
    {
        $data = [
            'type' => 'text',
            'text' => 'Unicode test: ñ 中文 🎉',
        ];

        $result = $this->denormalizer->denormalize($data, ContentPartInterface::class);

        $this->assertEquals('Unicode test: ñ 中文 🎉', $result->text);
    }

    public function testDenormalizeImageType(): void
    {
        $data = [
            'type' => 'image_url',
            'image_url' => [
                'url' => 'https://example.com/image.jpg',
            ],
        ];

        $result = $this->denormalizer->denormalize($data, ContentPartInterface::class);

        $this->assertInstanceOf(ImageContentPart::class, $result);
        $this->assertEquals('https://example.com/image.jpg', $result->url);
        $this->assertNull($result->detail);
        $this->assertEquals(ContentPartTypeEnum::IMAGE, $result->type);
    }

    public function testDenormalizeImageTypeWithAutoDetail(): void
    {
        $data = [
            'type' => 'image_url',
            'image_url' => [
                'url' => 'https://example.com/image.jpg',
                'detail' => 'auto',
            ],
        ];

        $result = $this->denormalizer->denormalize($data, ContentPartInterface::class);

        $this->assertInstanceOf(ImageContentPart::class, $result);
        $this->assertEquals(ImageDetailLevelEnum::AUTO, $result->detail);
    }

    public function testDenormalizeImageTypeWithLowDetail(): void
    {
        $data = [
            'type' => 'image_url',
            'image_url' => [
                'url' => 'https://example.com/image.jpg',
                'detail' => 'low',
            ],
        ];

        $result = $this->denormalizer->denormalize($data, ContentPartInterface::class);

        $this->assertInstanceOf(ImageContentPart::class, $result);
        $this->assertEquals(ImageDetailLevelEnum::LOW, $result->detail);
    }

    public function testDenormalizeImageTypeWithHighDetail(): void
    {
        $data = [
            'type' => 'image_url',
            'image_url' => [
                'url' => 'https://example.com/image.jpg',
                'detail' => 'high',
            ],
        ];

        $result = $this->denormalizer->denormalize($data, ContentPartInterface::class);

        $this->assertInstanceOf(ImageContentPart::class, $result);
        $this->assertEquals(ImageDetailLevelEnum::HIGH, $result->detail);
    }

    public function testDenormalizeImageTypeWithBase64Data(): void
    {
        $data = [
            'type' => 'image_url',
            'image_url' => [
                'url' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            ],
        ];

        $result = $this->denormalizer->denormalize($data, ContentPartInterface::class);

        $this->assertStringStartsWith('data:image/png;base64,', $result->url);
    }

    public function testDenormalizeThrowsExceptionForUnknownType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown content part type: unknown_type');

        $data = [
            'type' => 'unknown_type',
            'content' => 'test',
        ];

        $this->denormalizer->denormalize($data, ContentPartInterface::class);
    }

    public function testDenormalizeThrowsExceptionForMissingType(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $data = [
            'text' => 'test',
        ];

        $this->denormalizer->denormalize($data, ContentPartInterface::class);
    }

    public function testDenormalizeTextWithMultilineContent(): void
    {
        $data = [
            'type' => 'text',
            'text' => "Line 1\nLine 2\nLine 3",
        ];

        $result = $this->denormalizer->denormalize($data, ContentPartInterface::class);

        $this->assertStringContainsString("\n", $result->text);
        $this->assertEquals("Line 1\nLine 2\nLine 3", $result->text);
    }
}
