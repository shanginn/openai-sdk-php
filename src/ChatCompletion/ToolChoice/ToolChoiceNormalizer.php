<?php

declare(strict_types=1);

namespace App\Openai\ChatCompletion\ToolChoice;

use App\Openai\ChatCompletion\CompletionRequest\ToolChoice;
use App\Openai\ChatCompletion\CompletionRequest\ToolInterface;
use ArrayObject;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ToolChoiceNormalizer implements NormalizerInterface
{
    public function normalize(mixed $object, ?string $format = null, array $context = []): null|array|ArrayObject|bool|float|int|string
    {
        assert($object instanceof ToolChoice);

        $choice = [
            'type' => $object->type->value,
        ];

        if ($object->type === ToolChoiceType::REQUIRED && $object->tool !== null) {
            assert(is_a($object->tool, ToolInterface::class, true));

            $choice = [
                'type'     => 'function',
                'function' => [
                    'name' => $object->tool::getName(),
                ],
            ];
        }

        return $choice;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ToolChoice;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            ToolChoice::class => true,
        ];
    }
}
