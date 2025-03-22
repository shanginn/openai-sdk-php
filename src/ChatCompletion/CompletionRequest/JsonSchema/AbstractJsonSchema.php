<?php

declare(strict_types=1);

namespace Shanginn\Openai\ChatCompletion\CompletionRequest\JsonSchema;

use ReflectionClass;
use RuntimeException;

abstract class AbstractJsonSchema implements JsonSchemaInterface
{
    private static function getSchemaAttribute(): OpenaiSchema
    {
        $reflection = new ReflectionClass(static::class);
        $attributes = $reflection->getAttributes(OpenaiSchema::class);

        if (count($attributes) === 0) {
            throw new RuntimeException(sprintf(
                'Schema class %s must have an #[OpenaiSchema] attribute.',
                static::class
            ));
        }

        $openaiSchema = $attributes[0]->newInstance();
        assert($openaiSchema instanceof OpenaiSchema);

        return $openaiSchema;
    }

    public static function getName(): string
    {
        return self::getSchemaAttribute()->name;
    }

    public static function getDescription(): string
    {
        return self::getSchemaAttribute()->description;
    }

    public static function isStrict(): bool
    {
        return self::getSchemaAttribute()->isStrict;
    }
}
