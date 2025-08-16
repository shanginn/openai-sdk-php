<?php

declare(strict_types=1);

namespace Shanginn\Openai\ChatCompletion\CompletionRequest;

use Shanginn\Openai\ChatCompletion\CompletionRequest\JsonSchema\JsonSchemaInterface;
use InvalidArgumentException;

class ResponseFormat
{
    /**
     * @param ResponseFormatEnum                     $type
     * @param class-string<JsonSchemaInterface>|null $jsonSchema
     */
    public function __construct(
        public ResponseFormatEnum $type,
        public ?string $jsonSchema = null
    ) {
        if ($type === ResponseFormatEnum::JSON_SCHEMA) {
            if ($jsonSchema === null) {
                throw new InvalidArgumentException('json_schema format requires a schema');
            }

            if (!is_a($jsonSchema, JsonSchemaInterface::class, true)) {
                throw new InvalidArgumentException("Schema '$jsonSchema' must implement SchemaInterface");
            }
        }

        if ($type !== ResponseFormatEnum::JSON_SCHEMA && $jsonSchema !== null) {
            throw new InvalidArgumentException('only json_schema format requires a schema');
        }
    }
}