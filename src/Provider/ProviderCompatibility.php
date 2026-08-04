<?php

declare(strict_types=1);

namespace Shanginn\Openai\Provider;

use Shanginn\Openai\Exceptions\ProviderCompatibilityException;

final readonly class ProviderCompatibility
{
    public function __construct(
        public Provider $provider = Provider::OPENAI,
    ) {}

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function prepareChatCompletion(array $payload): array
    {
        return match ($this->provider) {
            Provider::OPENAI => $this->prepareOpenaiChatCompletion($payload),
            Provider::DEEPSEEK => $this->prepareDeepSeekChatCompletion($payload),
            Provider::QWEN => $this->prepareQwenChatCompletion($payload),
            Provider::XAI => $this->prepareXaiChatCompletion($payload),
            Provider::CUSTOM => $payload,
        };
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function prepareResponse(array $payload): array
    {
        if ($this->provider === Provider::QWEN && isset($payload['reasoning'])) {
            unset($payload['enable_thinking']);
        }

        if (
            $this->provider === Provider::DEEPSEEK
            && ($payload['model'] ?? null) === 'deepseek-v4-pro'
        ) {
            throw new ProviderCompatibilityException(
                'DeepSeek currently exposes deepseek-v4-pro through Chat Completions; '
                . 'use deepseek-v4-flash or switch this request to Chat Completions.',
            );
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function prepareOpenaiChatCompletion(array $payload): array
    {
        $model = (string) ($payload['model'] ?? '');

        if (isset($payload['thinking'])) {
            $thinking = $payload['thinking']['type'] ?? null;
            unset($payload['thinking']);

            if ($thinking === 'disabled') {
                $payload['reasoning_effort'] ??= 'none';
            }
        }

        if (
            $this->usesOpenaiCompletionTokenLimit($model)
            && isset($payload['max_tokens'])
            && !isset($payload['max_completion_tokens'])
        ) {
            $payload['max_completion_tokens'] = $payload['max_tokens'];
            unset($payload['max_tokens']);
        }

        if (
            $this->isGpt56Family($model)
            && !empty($payload['tools'])
            && !isset($payload['reasoning_effort'])
        ) {
            $payload['reasoning_effort'] = 'none';
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function prepareDeepSeekChatCompletion(array $payload): array
    {
        $this->useLegacyCompletionTokenLimit($payload);

        $thinkingType = $payload['thinking']['type'] ?? 'enabled';
        if ($thinkingType === 'disabled') {
            unset($payload['reasoning_effort']);
        } else {
            foreach ([
                'temperature',
                'top_p',
                'presence_penalty',
                'frequency_penalty',
                'logprobs',
                'top_logprobs',
            ] as $unsupportedParameter) {
                unset($payload[$unsupportedParameter]);
            }
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function prepareQwenChatCompletion(array $payload): array
    {
        $this->useLegacyCompletionTokenLimit($payload);

        if (isset($payload['thinking'])) {
            $payload['enable_thinking'] = ($payload['thinking']['type'] ?? null) !== 'disabled';
            unset($payload['thinking']);
        }

        if (($payload['enable_thinking'] ?? false) && isset($payload['response_format'])) {
            throw new ProviderCompatibilityException(
                'Qwen Chat Completions does not support structured output while thinking is enabled. '
                . 'Disable thinking or use the Responses API.',
            );
        }

        foreach ($payload['tools'] ?? [] as $tool) {
            if (($tool['function']['name'] ?? null) === 'search') {
                throw new ProviderCompatibilityException(
                    'Qwen reserves the tool name "search"; choose a different function name.',
                );
            }
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function prepareXaiChatCompletion(array $payload): array
    {
        $this->useLegacyCompletionTokenLimit($payload);
        unset($payload['thinking']);

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function useLegacyCompletionTokenLimit(array &$payload): void
    {
        if (isset($payload['max_completion_tokens']) && !isset($payload['max_tokens'])) {
            $payload['max_tokens'] = $payload['max_completion_tokens'];
            unset($payload['max_completion_tokens']);
        }
    }

    private function usesOpenaiCompletionTokenLimit(string $model): bool
    {
        return str_starts_with($model, 'gpt-5')
            || preg_match('/^o[134](?:-|$)/', $model) === 1;
    }

    private function isGpt56Family(string $model): bool
    {
        return $model === 'gpt-5.6' || str_starts_with($model, 'gpt-5.6-');
    }
}
