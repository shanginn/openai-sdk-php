<?php

declare(strict_types=1);

namespace App\Openai\ChatCompletion\Message\User;

use ArrayObject;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ImageContentPartNormalizer implements NormalizerInterface
{
    public function normalize(mixed $object, ?string $format = null, array $context = []): null|array|ArrayObject|bool|float|int|string
    {
        assert($object instanceof ImageContentPart);

        return [
            'type'      => $object->type,
            'image_url' => array_filter([
                'url'    => $object->url,
                'detail' => $object->detail,
            ]),
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ImageContentPart;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            ImageContentPart::class => true,
        ];
    }
}