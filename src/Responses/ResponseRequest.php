<?php

declare(strict_types=1);

namespace Shanginn\Openai\Responses;

final readonly class ResponseRequest
{
    /**
     * @param string|array<mixed>                              $input
     * @param array<string, mixed>|null                        $reasoning
     * @param array<string, mixed>|null                        $text
     * @param array<ResponseFunctionTool|array<string, mixed>>|null $tools
     * @param string|array<string, mixed>|null                 $toolChoice
     * @param array<string>|null                               $include
     * @param array<string, string>|null                       $metadata
     * @param array<string, mixed>|null                        $promptCacheOptions
     * @param array<string, mixed>                             $extraBody
     */
    public function __construct(
        public string $model,
        public string|array $input,
        public ?string $instructions = null,
        public ?int $maxOutputTokens = null,
        public ?array $reasoning = null,
        public ?array $text = null,
        public ?array $tools = null,
        public string|array|null $toolChoice = null,
        public ?bool $parallelToolCalls = null,
        public ?string $previousResponseId = null,
        public ?bool $store = null,
        public ?bool $stream = null,
        public ?array $include = null,
        public ?array $metadata = null,
        public ?string $safetyIdentifier = null,
        public ?string $promptCacheKey = null,
        public ?array $promptCacheOptions = null,
        public ?string $serviceTier = null,
        public ?float $temperature = null,
        public ?float $topP = null,
        public ?string $truncation = null,
        public array $extraBody = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = array_filter([
            'model' => $this->model,
            'input' => $this->input,
            'instructions' => $this->instructions,
            'max_output_tokens' => $this->maxOutputTokens,
            'reasoning' => $this->reasoning,
            'text' => $this->text,
            'tools' => $this->normalizeTools(),
            'tool_choice' => $this->toolChoice,
            'parallel_tool_calls' => $this->parallelToolCalls,
            'previous_response_id' => $this->previousResponseId,
            'store' => $this->store,
            'stream' => $this->stream,
            'include' => $this->include,
            'metadata' => $this->metadata,
            'safety_identifier' => $this->safetyIdentifier,
            'prompt_cache_key' => $this->promptCacheKey,
            'prompt_cache_options' => $this->promptCacheOptions,
            'service_tier' => $this->serviceTier,
            'temperature' => $this->temperature,
            'top_p' => $this->topP,
            'truncation' => $this->truncation,
        ], static fn(mixed $value): bool => $value !== null);

        return array_replace($payload, $this->extraBody);
    }

    /**
     * @return array<array<string, mixed>>|null
     */
    private function normalizeTools(): ?array
    {
        if ($this->tools === null) {
            return null;
        }

        return array_map(
            static fn(ResponseFunctionTool|array $tool): array => $tool instanceof ResponseFunctionTool
                ? $tool->toArray()
                : $tool,
            $this->tools,
        );
    }
}
