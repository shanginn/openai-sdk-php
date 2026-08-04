<?php

declare(strict_types=1);

namespace Shanginn\Openai;

use Shanginn\Openai\ChatCompletion\CompletionRequest\JsonSchema\JsonSchemaInterface;
use Shanginn\Openai\ChatCompletion\CompletionRequest\ResponseFormat;
use Shanginn\Openai\ChatCompletion\CompletionRequest\ResponseFormatEnum;
use Shanginn\Openai\ChatCompletion\CompletionRequest\ToolChoice;
use Shanginn\Openai\ChatCompletion\CompletionRequest\ToolInterface;
use Shanginn\Openai\ChatCompletion\ErrorResponse;
use Shanginn\Openai\ChatCompletion\Message\Assistant\KnownFunctionCall;
use Shanginn\Openai\ChatCompletion\Message\MessageInterface;
use Shanginn\Openai\ChatCompletion\Message\SchemedAssistantMessage;
use Shanginn\Openai\ChatCompletion\Message\UserMessage;
use Shanginn\Openai\Exceptions\OpenaiErrorResponseException;
use Shanginn\Openai\Exceptions\OpenaiNoChoicesException;
use Shanginn\Openai\Exceptions\OpenaiNoContentException;
use Shanginn\Openai\Exceptions\OpenaiRefusedResponseException;
use Shanginn\Openai\Exceptions\OpenaiWrongSchemaException;
use InvalidArgumentException;
use Shanginn\Openai\Openai\OpenaiClient;
use Shanginn\Openai\Provider\Provider;

class OpenaiSimple
{
    /**
     * @param array<string, mixed> $extraBody
     */
    public function __construct(
        protected readonly Openai $openai,
        private readonly ?bool $thinkingEnabled = null,
        private readonly ?string $reasoningEffort = null,
        private readonly array $extraBody = [],
    ) {}

    /**
     * @param array<string, mixed> $extraBody
     */
    public static function create(
        string $apiKey,
        string $model = 'gpt-5.6',
        string $apiUrl = 'https://api.openai.com/v1',
        ?bool $thinkingEnabled = null,
        ?string $reasoningEffort = null,
        array $extraBody = [],
        Provider $provider = Provider::OPENAI,
    ): self {
        $client = new OpenaiClient(
            apiKey: $apiKey,
            apiUrl: $apiUrl,
        );

        $openai = new Openai($client, $model, $provider);

        return new self(
            openai: $openai,
            thinkingEnabled: $thinkingEnabled,
            reasoningEffort: $reasoningEffort,
            extraBody: $extraBody,
        );
    }

    /**
     * @param string                       $system
     * @param string|UserMessage           $userMessage
     * @param array<MessageInterface>|null $history
     * @param ?string                      $schema
     * @param ?float                       $temperature
     * @param ?float                       $frequencyPenalty
     * @param ?int                         $maxCompletionTokens
     * @param ?int                         $maxTokens
     * @param ?float                       $topP
     * @param ?int                         $seed
     * @param ?string                      $reasoningEffort
     * @param ?bool                        $thinkingEnabled
     * @param array<string, mixed>|null    $extraBody
     *
     * @return JsonSchemaInterface
     */
    public function generate(
        string $system,
        string|UserMessage $userMessage,
        ?array $history = [],
        ?string $schema = null,
        ?float $temperature = null,
        ?float $frequencyPenalty = null,
        ?int $maxTokens = null,
        ?int $maxCompletionTokens = null,
        ?float $topP = null,
        ?int $seed = null,
        ?string $reasoningEffort = null,
        ?bool $thinkingEnabled = null,
        ?array $extraBody = null,
    ): JsonSchemaInterface|string {
        if ($schema !== null && !is_a($schema, JsonSchemaInterface::class, true)) {
            throw new InvalidArgumentException("Schema '$schema' must implement SchemaInterface");
        }

        $response = $this->openai->completion(
            messages: array_merge($history, [
                is_string($userMessage) ? new UserMessage($userMessage) : $userMessage,
            ]),
            system: $system,
            temperature: $temperature,
            maxTokens: $maxTokens,
            maxCompletionTokens: $maxCompletionTokens,
            frequencyPenalty: $frequencyPenalty,
            responseFormat: $schema !== null
                ? new ResponseFormat(ResponseFormatEnum::JSON_SCHEMA, $schema)
                : null,
            topP: $topP,
            seed: $seed,
            reasoningEffort: $this->buildReasoningEffort($reasoningEffort, $thinkingEnabled, $extraBody),
            extraBody: $this->buildExtraBody($thinkingEnabled, $extraBody),
        );

        if ($response instanceof ErrorResponse) {
            throw new OpenaiErrorResponseException($response);
        }

        if (!isset($response->choices) || count($response->choices) === 0) {
            throw new OpenaiNoChoicesException($response);
        }

        $message = $response->choices[0]->message;

        if ($message->refusal !== null) {
            throw new OpenaiRefusedResponseException($response);
        }

        if ($message->content === null) {
            throw new OpenaiNoContentException($response);
        }

        if ($schema === null) {
            return $message->content;
        }

        if (!$message instanceof SchemedAssistantMessage) {
            throw new OpenaiWrongSchemaException($response);
        }

        return $message->schemedContend;
    }

    /**
     * @template T of ToolInterface
     *
     * @param string          $system
     * @param string          $text
     * @param class-string<T> $tool
     * @param array|null      $history
     * @param float|null      $temperature
     * @param float|null      $frequencyPenalty
     * @param ?string         $reasoningEffort
     * @param ?bool           $thinkingEnabled
     * @param array<string, mixed>|null $extraBody
     *
     * @return T
     */
    public function callTool(
        string $system,
        string $text,
        string $tool,
        ?array $history = [],
        ?float $temperature = null,
        ?float $frequencyPenalty = null,
        ?string $reasoningEffort = null,
        ?bool $thinkingEnabled = null,
        ?array $extraBody = null,
    ): ToolInterface {
        $response = $this->openai->completion(
            messages: array_merge($history, [
                new UserMessage($text),
            ]),
            system: $system,
            temperature: $temperature,
            frequencyPenalty: $frequencyPenalty,
            reasoningEffort: $this->buildReasoningEffort($reasoningEffort, $thinkingEnabled, $extraBody),
            toolChoice: ToolChoice::useTool($tool),
            tools: [$tool],
            extraBody: $this->buildExtraBody($thinkingEnabled, $extraBody),
        );

        if ($response instanceof ErrorResponse) {
            throw new OpenaiErrorResponseException($response);
        }

        if (count($response->choices) === 0) {
            throw new OpenaiNoChoicesException($response);
        }

        $toolCalls = $response->choices[0]->message->toolCalls;

        if ($toolCalls === null || count($toolCalls) === 0) {
            throw new OpenaiWrongSchemaException($response);
        }

        $choice = $response->choices[0]->message->toolCalls[0] ?? null;

        if (!$choice instanceof KnownFunctionCall) {
            throw new OpenaiWrongSchemaException($response);
        }

        return $choice->arguments;
    }

    /**
     * @param array<string, mixed>|null $extraBody
     *
     * @return array<string, mixed>|null
     */
    private function buildExtraBody(?bool $thinkingEnabled, ?array $extraBody): ?array
    {
        $body = array_replace($this->extraBody, $extraBody ?? []);
        $effectiveThinkingEnabled = $thinkingEnabled ?? $this->thinkingEnabled;

        if ($effectiveThinkingEnabled !== null) {
            $body['thinking'] = [
                'type' => $effectiveThinkingEnabled ? 'enabled' : 'disabled',
            ];
        }

        return $body === [] ? null : $body;
    }

    /**
     * @param array<string, mixed>|null $extraBody
     */
    private function buildReasoningEffort(
        ?string $reasoningEffort,
        ?bool $thinkingEnabled,
        ?array $extraBody,
    ): ?string {
        if ($this->isThinkingDisabled($thinkingEnabled, $extraBody)) {
            return null;
        }

        return $reasoningEffort ?? $this->reasoningEffort;
    }

    /**
     * @param array<string, mixed>|null $extraBody
     */
    private function isThinkingDisabled(?bool $thinkingEnabled, ?array $extraBody): bool
    {
        $effectiveThinkingEnabled = $thinkingEnabled ?? $this->thinkingEnabled;

        if ($effectiveThinkingEnabled !== null) {
            return !$effectiveThinkingEnabled;
        }

        $body = array_replace($this->extraBody, $extraBody ?? []);

        return ($body['thinking']['type'] ?? null) === 'disabled';
    }
}
