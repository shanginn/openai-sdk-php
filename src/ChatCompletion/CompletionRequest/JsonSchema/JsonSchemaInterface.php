<?php

declare(strict_types=1);

namespace App\Openai\ChatCompletion\CompletionRequest\JsonSchema;

interface JsonSchemaInterface
{
    public static function getName(): string;

    public static function getDescription(): string;

    public static function isStrict(): bool;
}
