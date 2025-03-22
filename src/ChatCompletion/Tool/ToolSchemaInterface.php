<?php

declare(strict_types=1);

namespace Shanginn\Openai\ChatCompletion\Tool;

use Shanginn\Openai\ChatCompletion\CompletionRequest\ToolInterface;

interface ToolSchemaInterface
{
    /**
     * @return class-string<ToolInterface>
     */
    public static function getTool(): string;
}
