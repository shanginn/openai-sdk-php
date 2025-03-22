<?php

declare(strict_types=1);

namespace App\Openai\ChatCompletion\Tool;

use App\Openai\ChatCompletion\CompletionRequest\ToolInterface;
use ArrayObject;
use Spiral\JsonSchemaGenerator\Generator;
use Spiral\JsonSchemaGenerator\GeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final readonly class ToolNormalizer implements NormalizerInterface
{
    private GeneratorInterface $jsonSchemaGenerator;

    public function __construct()
    {
        $this->jsonSchemaGenerator = new Generator();
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): null|array|ArrayObject|bool|float|int|string
    {
        assert(is_a($object, ToolInterface::class, true));

        // TODO: откуда здесь взялся объект?
        if ($object instanceof ToolInterface) {
            $object = $object::class;
        }

        // TODO: если есть Enum'ы, добавлять их в схему
        return [
            'type'     => 'function',
            'function' => [
                'name'        => $object::getName(),
                'description' => $object::getDescription(),
                'parameters'  => [
                    'type' => 'object',
                    ...$this->jsonSchemaGenerator->generate($object)->jsonSerialize(),
                ],
            ],
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return is_a($data, ToolInterface::class, true);
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['*' => true, ToolInterface::class => true];
    }
}
