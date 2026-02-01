<?php

declare(strict_types=1);

namespace Shanginn\Openai\ChatCompletion\Message\User;

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class ContentPartDenormalizer implements DenormalizerInterface
{
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): ContentPartInterface
    {
        $typeValue = $data['type'] ?? null;

        return match ($typeValue) {
            ContentPartTypeEnum::TEXT->value => new TextContentPart(
                text: $data['text'],
            ),
            ContentPartTypeEnum::IMAGE->value => new ImageContentPart(
                url: $data['image_url']['url'],
                detail: isset($data['image_url']['detail'])
                    ? ImageDetailLevelEnum::from($data['image_url']['detail'])
                    : null,
            ),
            default => throw new \InvalidArgumentException("Unknown content part type: {$typeValue}"),
        };
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $type === ContentPartInterface::class;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            ContentPartInterface::class => true,
        ];
    }
}
