<?php

declare(strict_types=1);

namespace App\Openai\ChatCompletion\CompletionRequest\JsonSchema;

use ArrayObject;
use Spiral\JsonSchemaGenerator\Generator;
use Spiral\JsonSchemaGenerator\GeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final readonly class JsonSchemaNormalizer implements NormalizerInterface
{
    private GeneratorInterface $jsonSchemaGenerator;

    public function __construct()
    {
        $this->jsonSchemaGenerator = new Generator();
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): null|array|ArrayObject|bool|float|int|string
    {
        assert(is_a($object, JsonSchemaInterface::class, true));

        // TODO: если есть Enum'ы, добавлять их в схему
        return [
            'name'        => $object::getName(),
            'description' => $object::getDescription(),
            'strict'      => $object::isStrict(),
            'schema'      => [
                'type'                 => 'object',
                'additionalProperties' => false,
                ...$this->jsonSchemaGenerator->generate($object)->jsonSerialize(),
            ],
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return is_a($data, JsonSchemaInterface::class, true);
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['*' => true, JsonSchemaInterface::class => true];
    }
}
