<?php

declare(strict_types=1);

namespace Shanginn\Openai\Openai;

use Shanginn\Openai\ChatCompletion\CompletionRequest\JsonSchema\JsonSchemaNormalizer;
use Shanginn\Openai\ChatCompletion\CompletionRequest\ResponseFormatNormalizer;
use Shanginn\Openai\ChatCompletion\Message\AssistantMessageDenormalizer;
use Shanginn\Openai\ChatCompletion\Message\Assistant\ToolCallDenormalizer;
use Shanginn\Openai\ChatCompletion\Message\Assistant\ToolCallNormalizer;
use Shanginn\Openai\ChatCompletion\Message\Assistant\UnknownFunctionCallDenormalizer;
use Shanginn\Openai\ChatCompletion\Message\User\ContentPartDenormalizer;
use Shanginn\Openai\ChatCompletion\Message\User\ImageContentPartNormalizer;
use Shanginn\Openai\ChatCompletion\Tool\ToolNormalizer;
use Shanginn\Openai\ChatCompletion\ToolChoice\ToolChoiceNormalizer;
use Shanginn\Openai\Exceptions\OpenaiDeserializeException;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

class OpenaiSerializer implements OpenaiSerializerInterface
{
    public SerializerInterface $serializer;

    public function __construct()
    {
        $nameConverter = new CamelCaseToSnakeCaseNameConverter();

        // Property info extractors for type resolution
        $reflectionExtractor = new ReflectionExtractor();
        $phpDocExtractor = new PhpDocExtractor();
        $propertyInfoExtractor = new PropertyInfoExtractor(
            typeExtractors: [$phpDocExtractor, $reflectionExtractor]
        );

        // Default context for ObjectNormalizer
        $defaultContext = [
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
        ];

        // ObjectNormalizer with proper configuration
        $objectNormalizer = new ObjectNormalizer(
            nameConverter: $nameConverter,
            propertyTypeExtractor: $propertyInfoExtractor,
            defaultContext: $defaultContext,
        );

        $encoders = [new JsonEncoder()];
        $normalizers = [
            new BackedEnumNormalizer(),
            new JsonSchemaNormalizer(),
            new ResponseFormatNormalizer(),
            new ToolNormalizer(),
            new ToolChoiceNormalizer(),
            new ImageContentPartNormalizer(),
            new ToolCallNormalizer(),
            new UnknownFunctionCallDenormalizer(),
            new ToolCallDenormalizer(),
            new ContentPartDenormalizer(),
            new AssistantMessageDenormalizer(),
            // ArrayDenormalizer must come after specific denormalizers but before ObjectNormalizer
            new ArrayDenormalizer(),
            // ObjectNormalizer is the fallback
            $objectNormalizer,
        ];

        $this->serializer = new Serializer($normalizers, $encoders);
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
        try {
            return $this->serializer->deserialize(
                data: $serialized,
                type: $to,
                format: 'json'
            );
        } catch (\Throwable $e) {
            throw new OpenaiDeserializeException(
                serialized: $serialized,
                to: $to,
                previous: $e
            );
        }
    }
}
