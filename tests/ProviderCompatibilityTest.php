<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Shanginn\Openai\Exceptions\ProviderCompatibilityException;
use Shanginn\Openai\Provider\Provider;
use Shanginn\Openai\Provider\ProviderCompatibility;

final class ProviderCompatibilityTest extends TestCase
{
    public function testOpenaiAdaptsLatestChatCompletionParameters(): void
    {
        $payload = (new ProviderCompatibility(Provider::OPENAI))->prepareChatCompletion([
            'model' => 'gpt-5.6-terra',
            'max_tokens' => 100,
            'tools' => [['type' => 'function', 'function' => ['name' => 'weather']]],
        ]);

        $this->assertSame(100, $payload['max_completion_tokens']);
        $this->assertArrayNotHasKey('max_tokens', $payload);
        $this->assertSame('none', $payload['reasoning_effort']);
    }

    public function testDeepSeekRemovesUnsupportedThinkingParameters(): void
    {
        $payload = (new ProviderCompatibility(Provider::DEEPSEEK))->prepareChatCompletion([
            'model' => 'deepseek-v4-pro',
            'max_completion_tokens' => 100,
            'thinking' => ['type' => 'enabled'],
            'reasoning_effort' => 'max',
            'temperature' => 0.2,
            'top_p' => 0.8,
            'logprobs' => true,
        ]);

        $this->assertSame(100, $payload['max_tokens']);
        $this->assertArrayNotHasKey('max_completion_tokens', $payload);
        $this->assertArrayNotHasKey('temperature', $payload);
        $this->assertArrayNotHasKey('top_p', $payload);
        $this->assertArrayNotHasKey('logprobs', $payload);
        $this->assertSame('max', $payload['reasoning_effort']);
    }

    public function testDeepSeekKeepsSamplingParametersWhenThinkingIsDisabled(): void
    {
        $payload = (new ProviderCompatibility(Provider::DEEPSEEK))->prepareChatCompletion([
            'model' => 'deepseek-v4-flash',
            'thinking' => ['type' => 'disabled'],
            'reasoning_effort' => 'max',
            'temperature' => 0.2,
        ]);

        $this->assertSame(0.2, $payload['temperature']);
        $this->assertArrayNotHasKey('reasoning_effort', $payload);
    }

    public function testQwenTranslatesThinkingToggle(): void
    {
        $payload = (new ProviderCompatibility(Provider::QWEN))->prepareChatCompletion([
            'model' => 'qwen3.7-plus',
            'thinking' => ['type' => 'disabled'],
        ]);

        $this->assertFalse($payload['enable_thinking']);
        $this->assertArrayNotHasKey('thinking', $payload);
    }

    public function testQwenRejectsStructuredOutputWithThinking(): void
    {
        $this->expectException(ProviderCompatibilityException::class);

        (new ProviderCompatibility(Provider::QWEN))->prepareChatCompletion([
            'model' => 'qwen3.7-plus',
            'enable_thinking' => true,
            'response_format' => ['type' => 'json_object'],
        ]);
    }

    public function testQwenRejectsReservedSearchToolName(): void
    {
        $this->expectException(ProviderCompatibilityException::class);

        (new ProviderCompatibility(Provider::QWEN))->prepareChatCompletion([
            'model' => 'qwen3.7-plus',
            'tools' => [['type' => 'function', 'function' => ['name' => 'search']]],
        ]);
    }

    public function testQwenResponsesPrefersStandardReasoningControl(): void
    {
        $payload = (new ProviderCompatibility(Provider::QWEN))->prepareResponse([
            'model' => 'qwen3.7-plus',
            'input' => 'Hello',
            'reasoning' => ['effort' => 'high'],
            'enable_thinking' => true,
        ]);

        $this->assertSame(['effort' => 'high'], $payload['reasoning']);
        $this->assertArrayNotHasKey('enable_thinking', $payload);
    }

    public function testXaiChatUsesLegacyTokenLimitAndDropsForeignThinkingField(): void
    {
        $payload = (new ProviderCompatibility(Provider::XAI))->prepareChatCompletion([
            'model' => 'grok-4.5',
            'max_completion_tokens' => 100,
            'thinking' => ['type' => 'enabled'],
        ]);

        $this->assertSame(100, $payload['max_tokens']);
        $this->assertArrayNotHasKey('max_completion_tokens', $payload);
        $this->assertArrayNotHasKey('thinking', $payload);
    }

    public function testDeepSeekProIsRoutedToChatCompletions(): void
    {
        $this->expectException(ProviderCompatibilityException::class);

        (new ProviderCompatibility(Provider::DEEPSEEK))->prepareResponse([
            'model' => 'deepseek-v4-pro',
            'input' => 'Hello',
        ]);
    }
}
