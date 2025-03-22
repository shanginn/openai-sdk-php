<?php

declare(strict_types=1);

namespace Shanginn\Openai\ChatCompletion\CompletionRequest;

interface ToolInterface
{
    public static function getName(): string;

    public static function getDescription(): string;
}
