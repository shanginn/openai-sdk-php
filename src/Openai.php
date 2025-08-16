<?php

declare(strict_types=1);

namespace Shanginn\Openai;

use Shanginn\Openai\ChatCompletion\CompletionRequest;
use Shanginn\Openai\ChatCompletion\CompletionRequest\ResponseFormat;
use Shanginn\Openai\ChatCompletion\CompletionRequest\ResponseFormatEnum;
use Shanginn\Openai\ChatCompletion\CompletionRequest\ToolChoice;
use Shanginn\Openai\ChatCompletion\CompletionRequest\ToolInterface;
use Shanginn\Openai\ChatCompletion\CompletionResponse;
use Shanginn\Openai\ChatCompletion\ErrorResponse;
use Shanginn\Openai\ChatCompletion\Message\Assistant\KnownFunctionCall;
use Shanginn\Openai\ChatCompletion\Message\Assistant\UnknownFunctionCall;
use Shanginn\Openai\ChatCompletion\Message\AssistantMessage;
use Shanginn\Openai\ChatCompletion\Message\MessageInterface;
use Shanginn\Openai\ChatCompletion\Message\SystemMessage;
use Shanginn\Openai\Openai\OpenaiClientInterface;
use Shanginn\Openai\Openai\OpenaiSerializer;
use Shanginn\Openai\Openai\OpenaiSerializerInterface;
use Throwable;

class Openai
{
    private readonly OpenaiSerializerInterface $serializer;

    public function __construct(
        private readonly OpenaiClientInterface $client,
        private readonly string $model = 'gpt-5-mini',
    ) {
        $this->serializer = new OpenaiSerializer();
    }

    /**
     * Sends a message to the model and retrieves the response.
     *
     * @param array<MessageInterface>                 $messages         array of input messages
     * @param ?string                                 $system           the system message to send to the model
     * @param ?float                                  $temperature      What sampling temperature to use, between 0 and 2. Higher values like 0.8 will make the output more random
     * @param ?int                                    $maxTokens        the maximum number of tokens to generate before stopping
     * @param ToolChoice|null                         $toolChoice       specifies how the model should use the provided tools
     * @param array<class-string<ToolInterface>>|null $tools            definitions and descriptions of tools that the model may use during the response generation
     * @param ?ResponseFormat                         $responseFormat
     * @param ?float                                  $frequencyPenalty
     * @param ?float                                  $topP
     * @param ?int                                    $seed
     *
     * @return CompletionResponse|ErrorResponse
     */
    public function completion(
        array $messages,
        ?string $system = null,
        ?float $temperature = 0.0,
        ?int $maxTokens = 1024,
        ?float $frequencyPenalty = null,
        ?ToolChoice $toolChoice = null,
        ?array $tools = null,
        ?ResponseFormat $responseFormat = null,
        ?float $topP = null,
        ?int $seed = null,
    ): CompletionResponse|ErrorResponse {
        if ($system !== null) {
            array_unshift($messages, new SystemMessage($system));
        }

        $request = new CompletionRequest(
            model: $this->model,
            messages: $messages,
            temperature: $temperature,
            maxTokens: $maxTokens,
            frequencyPenalty: $frequencyPenalty,
            responseFormat: $responseFormat,
            seed: $seed,
            topP: $topP,
            tools: $tools,
            toolChoice: $toolChoice,
        );

        $body = $this->serializer->serialize($request);

        $responseJson = $this->client->sendRequest('/chat/completions', $body);

        $responseData = json_decode($responseJson, associative: true);

        if (isset($responseData['error'])) {
            if (is_string($responseData['error'])) {
                $errorMessage = $responseData['error']; // формат x.ai
            } else {
                $errorMessage = $responseData['error']['message'] ?? null;
            }

            return new ErrorResponse(
                message: $errorMessage,
                type: $responseData['error']['type'] ?? null,
                param: $responseData['error']['param'] ?? null,
                code: $responseData['error']['code'] ?? $responseData['code'] ?? null,
            );
        }

        /** @var CompletionResponse $response */
        $response = $this->serializer->deserialize($responseJson, CompletionResponse::class);

        /** @var array<string,class-string<ToolInterface>> $toolsMap */
        $toolsMap = array_merge(...array_map(
            fn (string $tool) => [$tool::getName() => $tool],
            $tools ?? []
        ));

        foreach ($response->choices as $i => $choice) {
            if ($choice->message instanceof AssistantMessage) {
                if (count($choice->message->toolCalls ?? []) > 0) {
                    $response->choices[$i] = CompletionResponse\Choice::withToolCalls(
                        $choice,
                        $this->convertKnownToolCalls(
                            $choice,
                            $toolsMap,
                        )
                    );
                }

                if ($responseFormat?->type === ResponseFormatEnum::JSON_SCHEMA) {
                    try {
                        $schemedContent = $this->serializer->deserialize(
                            serialized: $choice->message->content,
                            to: $responseFormat->jsonSchema
                        );

                        $response->choices[$i] = CompletionResponse\Choice::withSchemedMessage(
                            $choice,
                            $schemedContent,
                        );
                    } catch (Throwable $e) {
                        continue;
                    }
                }
            }
        }

        return $response;
    }

    /**
     * @param CompletionResponse\Choice          $choice
     * @param array<class-string<ToolInterface>> $toolsMap
     *
     * @return list<UnknownFunctionCall|KnownFunctionCall>
     */
    public function convertKnownToolCalls(CompletionResponse\Choice $choice, array $toolsMap): array
    {
        $toolCalls = [];

        foreach ($choice->message->toolCalls as $calledTool) {
            if (!$calledTool instanceof UnknownFunctionCall || !isset($toolsMap[$calledTool->name])) {
                $toolCalls[] = $calledTool;

                continue;
            }

            $tool = $toolsMap[$calledTool->name];

            try {
                $toolInput = $this->serializer->deserialize(
                    serialized: $calledTool->arguments,
                    to: $tool,
                );
            } catch (Throwable $e) {
                continue;
            }

            $toolCalls[] = new KnownFunctionCall(
                id: $calledTool->id,
                tool: $tool,
                arguments: $toolInput,
            );
        }

        return $toolCalls;
    }
}
