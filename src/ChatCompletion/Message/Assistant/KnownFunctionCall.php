<?php

declare(strict_types=1);

namespace App\Openai\ChatCompletion\Message\Assistant;

use App\Openai\ChatCompletion\CompletionRequest\ToolInterface;

final readonly class KnownFunctionCall implements ToolCallInterface
{
    public ToolCallTypeEnum $type;

    /**
     * @param string                      $id        the ID of the tool call
     * @param class-string<ToolInterface> $tool      the tool class of the called function
     * @param ToolInterface               $arguments The arguments to call the function with
     */
    public function __construct(
        public string $id,
        public string $tool,
        public ToolInterface $arguments,
    ) {
        $this->type = ToolCallTypeEnum::FUNCTION;
    }
}