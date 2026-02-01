<?php

declare(strict_types=1);

namespace Shanginn\Openai\ChatCompletion\Message\Assistant;

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class ToolCallDenormalizer implements DenormalizerInterface
{
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): ToolCallInterface
    {
        $typeValue = $data['type'] ?? null;

        // For now, only function type is supported
        if ($typeValue === ToolCallTypeEnum::FUNCTION->value) {
            return $this->denormalizeFunctionCall($data);
        }

        throw new \InvalidArgumentException("Unknown tool call type: {$typeValue}");
    }

    private function denormalizeFunctionCall(array $data): UnknownFunctionCall
    {
        return new UnknownFunctionCall(
            id: $data['id'],
            name: $data['function']['name'],
            arguments: $data['function']['arguments'],
        );
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $type === ToolCallInterface::class;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            ToolCallInterface::class => true,
        ];
    }
}
