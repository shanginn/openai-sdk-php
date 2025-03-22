<?php

declare(strict_types=1);

namespace App\Openai\ChatCompletion\CompletionRequest;

interface ToolInterface
{
    public static function getName(): string;

    public static function getDescription(): string;
}
