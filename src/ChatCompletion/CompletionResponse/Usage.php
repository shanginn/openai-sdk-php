<?php

declare(strict_types=1);

namespace Shanginn\Openai\ChatCompletion\CompletionResponse;

/**
 * Represents the usage statistics for the completion request.
 */
final class Usage
{
    /**
     * @param int $completionTokens Number of tokens in the generated completion
     * @param int $promptTokens     Number of tokens in the prompt
     * @param int $totalTokens      Total number of tokens used in the request (prompt + completion)
     */
    public function __construct(
        public int $completionTokens,
        public int $promptTokens,
        public int $totalTokens,
    ) {}
}
