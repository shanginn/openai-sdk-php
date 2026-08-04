<?php

declare(strict_types=1);

namespace Shanginn\Openai\ChatCompletion\CompletionResponse;

use Crell\Serde\Attributes as Serde;
use Crell\Serde\Renaming\Cases;

/**
 * Represents the usage statistics for the completion request.
 */
#[Serde\ClassSettings(
    renameWith: Cases::snake_case,
    omitNullFields: true
)]
final class Usage
{
    /**
     * @param int                       $completionTokens       Number of tokens in the generated completion
     * @param int                       $promptTokens           Number of tokens in the prompt
     * @param int                       $totalTokens            Total number of tokens used in the request
     * @param array<string, mixed>|null $completionTokensDetails Provider-specific completion token details
     * @param array<string, mixed>|null $promptTokensDetails     Provider-specific prompt token details
     * @param int|null                  $promptCacheHitTokens    DeepSeek prompt cache hits
     * @param int|null                  $promptCacheMissTokens   DeepSeek prompt cache misses
     */
    public function __construct(
        public int $completionTokens,
        public int $promptTokens,
        public int $totalTokens,
        public ?array $completionTokensDetails = null,
        public ?array $promptTokensDetails = null,
        public ?int $promptCacheHitTokens = null,
        public ?int $promptCacheMissTokens = null,
    ) {}
}
