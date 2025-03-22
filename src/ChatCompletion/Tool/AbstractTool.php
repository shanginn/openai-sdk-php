<?php

declare(strict_types=1);

namespace App\Openai\ChatCompletion\Tool;

use App\Openai\ChatCompletion\CompletionRequest\ToolInterface;
use ReflectionClass;
use RuntimeException;

abstract class AbstractTool implements ToolInterface
{
    private static function getSchemaAttribute(): OpenaiToolSchema
    {
        $reflection = new ReflectionClass(static::class);
        $attributes = $reflection->getAttributes(OpenaiToolSchema::class);

        if (count($attributes) === 0) {
            throw new RuntimeException(sprintf(
                'Schema class %s must have an #[OpenaiToolSchema] attribute.',
                static::class
            ));
        }

        $openaiSchema = $attributes[0]->newInstance();
        assert($openaiSchema instanceof OpenaiToolSchema);

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
}
