<?php

declare(strict_types=1);

namespace Shanginn\Openai\ChatCompletion\Message\Assistant;

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class UnknownFunctionCallDenormalizer implements DenormalizerInterface
{
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): UnknownFunctionCall
    {
        return new UnknownFunctionCall(
            id: $data['id'],
            name: $data['function']['name'],
            arguments: $data['function']['arguments'],
        );
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $type === UnknownFunctionCall::class;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            UnknownFunctionCall::class => true,
        ];
    }
}
