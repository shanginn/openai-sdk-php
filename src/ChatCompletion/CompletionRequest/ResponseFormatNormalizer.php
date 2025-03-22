<?php

declare(strict_types=1);

namespace Shanginn\Openai\ChatCompletion\CompletionRequest;

use ArrayObject;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ResponseFormatNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    public function normalize(mixed $object, ?string $format = null, array $context = []): null|array|ArrayObject|bool|float|int|string
    {
        assert($object instanceof ResponseFormat && $object->type === ResponseFormatEnum::JSON_SCHEMA);

        return [
            'type'        => $object->type->value,
            'json_schema' => $this->normalizer->normalize(
                $object->jsonSchema,
                $format,
                $context,
            ),
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ResponseFormat && $data->type === ResponseFormatEnum::JSON_SCHEMA;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [ResponseFormat::class => true];
    }
}
