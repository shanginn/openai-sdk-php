<?php

declare(strict_types=1);

namespace App\Openai\ChatCompletion\Tool;

use App\Openai\ChatCompletion\CompletionRequest\ToolInterface;

interface ToolSchemaInterface
{
    /**
     * @return class-string<ToolInterface>
     */
    public static function getTool(): string;
}
