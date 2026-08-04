<?php

declare(strict_types=1);

namespace Shanginn\Openai;

use Async\Coroutine;
use JsonException;
use Shanginn\Openai\ChatCompletion\CompletionRequest;
use Shanginn\Openai\ChatCompletion\CompletionRequest\ResponseFormat;
use Shanginn\Openai\ChatCompletion\CompletionRequest\ResponseFormatEnum;
use Shanginn\Openai\ChatCompletion\CompletionRequest\ToolChoice;
use Shanginn\Openai\ChatCompletion\CompletionRequest\ToolInterface;
use Shanginn\Openai\ChatCompletion\CompletionResponse;
use Shanginn\Openai\ChatCompletion\ErrorResponse;
use Shanginn\Openai\ChatCompletion\Message\AssistantMessage;
use Shanginn\Openai\ChatCompletion\Message\MessageInterface;
use Shanginn\Openai\ChatCompletion\Message\SystemMessage;
use Shanginn\Openai\Openai\OpenaiClientInterface;
use Shanginn\Openai\Openai\OpenaiSerializer;
use Shanginn\Openai\Openai\OpenaiSerializerInterface;
use Shanginn\Openai\Provider\Provider;
use Shanginn\Openai\Provider\ProviderCompatibility;
use Shanginn\Openai\Responses\Response;
use Shanginn\Openai\Responses\ResponseRequest;
use Throwable;

use function Async\spawn;

class Openai
{
    private readonly OpenaiSerializerInterface $serializer;
    private readonly ProviderCompatibility $compatibility;

    public function __construct(
        private readonly OpenaiClientInterface $client,
        private readonly string $model = 'gpt-5.6',
        Provider $provider = Provider::OPENAI,
    ) {
        $this->serializer = new OpenaiSerializer();
        $this->compatibility = new ProviderCompatibility($provider);
    }

    /**
     * @param array<MessageInterface>                 $messages
     * @param array<class-string<ToolInterface>>|null $tools
     * @param array<string, mixed>|null               $extraBody
     */
    public function completion(
        array $messages,
        ?string $system = null,
        ?float $temperature = null,
        ?int $maxTokens = null,
        ?int $maxCompletionTokens = null,
        ?float $frequencyPenalty = null,
        ?ToolChoice $toolChoice = null,
        ?array $tools = null,
        ?ResponseFormat $responseFormat = null,
        ?float $topP = null,
        ?int $seed = null,
        ?string $reasoningEffort = null,
        ?array $extraBody = null,
    ): CompletionResponse|ErrorResponse {
        if ($system !== null) {
            array_unshift($messages, new SystemMessage($system));
        }

        return $this->sendCompletionRequest(
            new CompletionRequest(
                model: $this->model,
                messages: $messages,
                temperature: $temperature,
                maxTokens: $maxTokens,
                maxCompletionTokens: $maxCompletionTokens,
                reasoningEffort: $reasoningEffort,
                frequencyPenalty: $frequencyPenalty,
                responseFormat: $responseFormat,
                seed: $seed,
                topP: $topP,
                tools: $tools,
                toolChoice: $toolChoice,
            ),
            extraBody: $extraBody,
        );
    }

    /**
     * @param array<string, mixed>|null $extraBody
     */
    public function sendCompletionRequest(
        CompletionRequest $request,
        ?array $extraBody = null,
    ): CompletionResponse|ErrorResponse {
        $payload = $this->serializedObjectToArray($request);

        if ($extraBody !== null) {
            $payload = array_replace($payload, $extraBody);
        }

        $payload = $this->compatibility->prepareChatCompletion($payload);
        $responseJson = $this->client->sendRequest(
            '/chat/completions',
            json_encode($payload, JSON_THROW_ON_ERROR),
        );

        $error = $this->parseError($responseJson);
        if ($error !== null) {
            return $error;
        }

        /** @var CompletionResponse $response */
        $response = $this->serializer->deserialize(
            $responseJson,
            CompletionResponse::class,
            $request->tools,
        );
        $response->raw = json_decode($responseJson, true, flags: JSON_THROW_ON_ERROR);

        return $this->applyStructuredOutput($response, $request->responseFormat);
    }

    /**
     * @param array<string, mixed>|null $extraBody
     *
     * @return Coroutine<CompletionResponse|ErrorResponse>
     */
    public function sendCompletionRequestAsync(
        CompletionRequest $request,
        ?array $extraBody = null,
    ): Coroutine {
        return spawn(fn(): CompletionResponse|ErrorResponse => $this->sendCompletionRequest(
            $request,
            $extraBody,
        ));
    }

    /**
     * @param array<string, mixed>|null $extraBody
     *
     * @return iterable<array<string, mixed>>
     */
    public function streamCompletion(
        CompletionRequest $request,
        ?array $extraBody = null,
    ): iterable {
        $payload = $this->serializedObjectToArray($request);
        $payload = array_replace($payload, $extraBody ?? [], ['stream' => true]);
        $payload = $this->compatibility->prepareChatCompletion($payload);

        foreach ($this->client->streamRequest(
            '/chat/completions',
            json_encode($payload, JSON_THROW_ON_ERROR),
        ) as $event) {
            yield json_decode($event, true, flags: JSON_THROW_ON_ERROR);
        }
    }

    public function response(ResponseRequest $request): Response|ErrorResponse
    {
        $payload = $this->compatibility->prepareResponse($request->toArray());
        $payloadJson = $this->serializer->serialize($payload);
        $responseJson = $this->client->sendRequest('/responses', $payloadJson);

        $error = $this->parseError($responseJson);
        if ($error !== null) {
            return $error;
        }

        return Response::fromJson($responseJson);
    }

    /**
     * @return Coroutine<Response|ErrorResponse>
     */
    public function responseAsync(ResponseRequest $request): Coroutine
    {
        return spawn(fn(): Response|ErrorResponse => $this->response($request));
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    public function streamResponse(ResponseRequest $request): iterable
    {
        $payload = array_replace($request->toArray(), ['stream' => true]);
        $payload = $this->compatibility->prepareResponse($payload);
        $payloadJson = $this->serializer->serialize($payload);

        foreach ($this->client->streamRequest('/responses', $payloadJson) as $event) {
            yield json_decode($event, true, flags: JSON_THROW_ON_ERROR);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializedObjectToArray(object $object): array
    {
        return json_decode(
            $this->serializer->serialize($object),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @throws JsonException
     */
    private function parseError(string $responseJson): ?ErrorResponse
    {
        $responseData = json_decode($responseJson, true, flags: JSON_THROW_ON_ERROR);

        if (!array_key_exists('error', $responseData)) {
            return null;
        }

        $error = $responseData['error'];
        if (is_string($error)) {
            return new ErrorResponse(
                message: $error,
                type: null,
                param: null,
                code: $this->stringOrInt($responseData['code'] ?? null),
                rawResponse: $responseJson,
            );
        }

        $error = is_array($error) ? $error : [];

        return new ErrorResponse(
            message: $this->nullableString($error['message'] ?? null),
            type: $this->nullableString($error['type'] ?? null),
            param: $this->nullableString($error['param'] ?? null),
            code: $this->stringOrInt($error['code'] ?? $responseData['code'] ?? null),
            rawResponse: $responseJson,
        );
    }

    private function applyStructuredOutput(
        CompletionResponse $response,
        ?ResponseFormat $responseFormat,
    ): CompletionResponse {
        if ($responseFormat?->type !== ResponseFormatEnum::JSON_SCHEMA) {
            return $response;
        }

        foreach ($response->choices as $index => $choice) {
            if (!$choice->message instanceof AssistantMessage || $choice->message->content === null) {
                continue;
            }

            try {
                $schemedContent = $this->serializer->deserialize(
                    serialized: $choice->message->content,
                    to: $responseFormat->jsonSchema,
                );

                $response->choices[$index] = CompletionResponse\Choice::withSchemedMessage(
                    $choice,
                    $schemedContent,
                );
            } catch (Throwable) {
                continue;
            }
        }

        return $response;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    private function stringOrInt(mixed $value): null|int|string
    {
        return is_int($value) || is_string($value) ? $value : null;
    }
}
