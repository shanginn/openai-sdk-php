<?php

declare(strict_types=1);

namespace Tests;

use Shanginn\Openai\ChatCompletion\CompletionRequest\JsonSchema\AbstractJsonSchema;
use Shanginn\Openai\ChatCompletion\CompletionRequest\JsonSchema\OpenaiSchema;
use Spiral\JsonSchemaGenerator\Attribute\Field;

#[OpenaiSchema(
    name: 'test_schema',
    description: 'A test schema'
)]
class SampleJsonSchema extends AbstractJsonSchema
{
    public function __construct(
        #[Field(title: 'Result')]
        public string $result
    ) {}
}