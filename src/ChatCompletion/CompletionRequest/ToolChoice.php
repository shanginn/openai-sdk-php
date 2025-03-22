<?php

declare(strict_types=1);

namespace App\Openai\ChatCompletion\CompletionRequest;

use App\Openai\ChatCompletion\ToolChoice\ToolChoiceType;
use InvalidArgumentException;

/**
 * Controls which (if any) tool is called by the model.
 * none means the model will not call any tool and instead generates a message.
 * auto means the model can pick between generating a message or calling one or more tools.
 * required means the model must call one or more tools.
 * Specifying a particular tool via {"type": "function", "function": {"name": "my_function"}}
 * forces the model to call that tool.
 *
 * none is the default when no tools are present. auto is the default if tools are present.
 */
final readonly class ToolChoice
{
    /**
     * @param ToolChoiceType                   $type
     * @param class-string<ToolInterface>|null $tool
     */
    public function __construct(
        public ToolChoiceType $type,
        public ?string $tool = null,
    ) {
        if ($type === ToolChoiceType::REQUIRED) {
            if ($tool !== null && !is_a($tool, ToolInterface::class, true)) {
                throw new InvalidArgumentException('Tool must implement ToolInterface.');
            }
        }
    }

    /**
     * @param class-string<ToolInterface> $tool
     *
     * @return self
     */
    public static function useTool(string $tool): self
    {
        if (!is_a($tool, ToolInterface::class, true)) {
            throw new InvalidArgumentException('Tool is not a ToolInterface');
        }

        return new self(ToolChoiceType::REQUIRED, $tool);
    }
}
