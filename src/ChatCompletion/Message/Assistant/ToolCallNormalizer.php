<?php

declare(strict_types=1);

namespace Shanginn\Openai\ChatCompletion\Message\Assistant;

use ArrayObject;
use Shanginn\Openai\ChatCompletion\CompletionRequest\ToolInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final readonly class ToolCallNormalizer implements NormalizerInterface
{
    public function normalize(mixed $object, ?string $format = null, array $context = []): null|array|ArrayObject|bool|float|int|string
    {
        assert($object instanceof KnownFunctionCall);

        return [
            'id'       => $object->id,
            'type'     => $object->type->value,
            'function' => [
                'name'      => $object->tool::getName(),
                'arguments' => json_encode($object->arguments),
            ],
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof KnownFunctionCall;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            KnownFunctionCall::class => true,
        ];
    }
}
