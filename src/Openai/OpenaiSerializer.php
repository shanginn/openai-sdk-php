<?php

declare(strict_types=1);

namespace App\Openai\Openai;

use App\Openai\ChatCompletion\CompletionRequest\JsonSchema\JsonSchemaNormalizer;
use App\Openai\ChatCompletion\CompletionRequest\ResponseFormatNormalizer;
use App\Openai\ChatCompletion\Message\Assistant\UnknownFunctionCallImporter;
use App\Openai\ChatCompletion\Message\User\ImageContentPartNormalizer;
use App\Openai\ChatCompletion\Tool\ToolNormalizer;
use App\Openai\ChatCompletion\ToolChoice\ToolChoiceNormalizer;
use Crell\Serde\SerdeCommon;
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
            new ObjectNormalizer(
                nameConverter: new CamelCaseToSnakeCaseNameConverter()
            ),
        ];

        $this->serializer = new Serializer($normalizers, $encoders);

        $this->deserializer = new SerdeCommon(
            handlers: [
                new UnknownFunctionCallImporter(),
            ]
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

    public function deserialize(mixed $serialized, string $to): object
    {
        return $this->deserializer->deserialize(
            serialized: $serialized,
            from: 'json',
            to: $to
        );
    }
}