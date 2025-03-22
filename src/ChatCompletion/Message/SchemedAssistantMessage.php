<?php

declare(strict_types=1);

namespace Shanginn\Openai\ChatCompletion\Message;

use Shanginn\Openai\ChatCompletion\CompletionRequest\JsonSchema\JsonSchemaInterface;

class SchemedAssistantMessage extends AssistantMessage
{
    /**
     * @param JsonSchemaInterface $schemedContend
     * @param mixed               ...$args
     */
    public function __construct(
        public JsonSchemaInterface $schemedContend,
        ...$args
    ) {
        parent::__construct(...$args);
    }

    public static function fromAssistantMessage(
        AssistantMessage $assistantMessage,
        JsonSchemaInterface $schemedContent
    ): self {
        return new self(
            $schemedContent,
            $assistantMessage->content,
            $assistantMessage->name,
            $assistantMessage->refusal,
            $assistantMessage->toolCalls,
        );
    }
}
