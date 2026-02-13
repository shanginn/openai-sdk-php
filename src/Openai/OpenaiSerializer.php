<?php

declare(strict_types=1);

namespace Shanginn\Openai\Openai;

use Crell\Serde\SerdeCommon;
use Shanginn\Openai\ChatCompletion\CompletionRequest\JsonSchema\JsonSchemaNormalizer;
use Shanginn\Openai\ChatCompletion\CompletionRequest\ResponseFormatNormalizer;
use Shanginn\Openai\ChatCompletion\CompletionRequest\ToolInterface;
use Shanginn\Openai\ChatCompletion\Message\Assistant\ToolCallImporter;
use Shanginn\Openai\ChatCompletion\Message\Assistant\ToolCallNormalizer;
use Shanginn\Openai\ChatCompletion\Message\User\ImageContentPartNormalizer;
use Shanginn\Openai\ChatCompletion\Tool\ToolNormalizer;
use Shanginn\Openai\ChatCompletion\ToolChoice\ToolChoiceNormalizer;
use Shanginn\Openai\Exceptions\OpenaiDeserializeException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

class OpenaiSerializer implements OpenaiSerializerInterface
{
    private SerdeCommon $deserializer;
    private ToolCallImporter $toolCallImporter;
    public SerializerInterface $serializer;

    public function __construct()
    {
        $encoders    = [new JsonEncoder()];
        $normalizers = [
            new BackedEnumNormalizer(),
            new JsonSchemaNormalizer(),
            new ResponseFormatNormalizer(),
            new ToolNormalizer(),
            new ToolChoiceNormalizer(),
            new ImageContentPartNormalizer(),
            new ToolCallNormalizer(),
            new ObjectNormalizer(
                nameConverter: new CamelCaseToSnakeCaseNameConverter()
            ),
        ];

        $this->serializer = new Serializer($normalizers, $encoders);

        $this->toolCallImporter = new ToolCallImporter();

        $this->deserializer = new SerdeCommon(
            handlers: [
                $this->toolCallImporter,
            ],
        );
    }

    public function serialize(mixed $data): string
    {
        return $this->serializer->serialize(
            data: $data,
            format: 'json',
            context: [AbstractObjectNormalizer::SKIP_NULL_VALUES => true]
        );
    }

    /**
     * @param array<class-string<ToolInterface>>|null $tools
     *
     * @throws OpenaiDeserializeException
     */
    public function deserialize(mixed $serialized, string $to, ?array $tools = null): object|array
    {
        // Set tools map if provided
        if (!empty($tools)) {
            $this->toolCallImporter->setTools($tools);
        }

        // Handle array deserialization
        if ($to === 'array') {
            return $this->deserializeArray($serialized);
        }

        try {
            $result = $this->deserializer->deserialize(
                serialized: $serialized,
                from: 'json',
                to: $to
            );
        } catch (\Throwable $e) {
            throw new OpenaiDeserializeException(
                serialized: $serialized,
                to: $to,
                previous: $e
            );
        }

        return $result;
    }

    /**
     * Deserialize an array of messages using Crell\Serde's type map.
     *
     * Uses MessagesArray as a wrapper to leverage the BackedEnumTypeMap
     * on MessageInterface for automatic polymorphic deserialization.
     *
     * @throws OpenaiDeserializeException
     */
    private function deserializeArray(mixed $serialized): array
    {
        try {
            // Wrap the JSON array in an object structure for deserialization
            $wrappedJson = json_encode(['messages' => json_decode($serialized, true)]);

            $result = $this->deserializer->deserialize(
                serialized: $wrappedJson,
                from: 'json',
                to: MessagesArray::class
            );

            return $result->messages;
        } catch (\Throwable $e) {
            throw new OpenaiDeserializeException(
                serialized: $serialized,
                to: 'array',
                previous: $e
            );
        }
    }
}