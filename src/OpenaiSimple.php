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

class OpenaiSimple
{
    public function __construct(
        protected Openai $openai,
    ) {}

    /**
     * @param string                       $system
     * @param string|UserMessage           $userMessage
     * @param array<MessageInterface>|null $history
     * @param ?string                      $schema
     * @param ?float                       $temperature
     * @param ?float                       $frequencyPenalty
     * @param ?int                         $maxTokens
     * @param ?float                       $topP
     * @param ?int                         $seed
     *
     * @return JsonSchemaInterface
     */
    public function generate(
        string $system,
        string|UserMessage $userMessage,
        ?array $history = [],
        ?string $schema = null,
        ?float $temperature = 0.0,
        ?float $frequencyPenalty = null,
        ?int $maxTokens = null,
        ?float $topP = null,
        ?int $seed = null,
    ): JsonSchemaInterface|string {
        if ($schema !== null && !is_a($schema, JsonSchemaInterface::class, true)) {
            throw new InvalidArgumentException('schema must implement SchemaInterface');
        }

        $response = $this->openai->completion(
            messages: array_merge($history, [
                is_string($userMessage) ? new UserMessage($userMessage) : $userMessage,
            ]),
            system: $system,
            temperature: $temperature,
            maxTokens: $maxTokens,
            frequencyPenalty: $frequencyPenalty,
            responseFormat: $schema !== null
                ? new ResponseFormat(ResponseFormatEnum::JSON_SCHEMA, $schema)
                : null,
            topP: $topP,
            seed: $seed,
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
     *
     * @return T
     */
    public function callTool(
        string $system,
        string $text,
        string $tool,
        ?array $history = [],
        ?float $temperature = 0.0,
        ?float $frequencyPenalty = 0.0,
    ): ToolInterface {
        $response = $this->openai->completion(
            messages: array_merge($history, [
                new UserMessage($text),
            ]),
            system: $system,
            temperature: $temperature,
            frequencyPenalty: $frequencyPenalty,
            toolChoice: ToolChoice::useTool($tool),
            tools: [$tool],
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
}