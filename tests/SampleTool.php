<?php

declare(strict_types=1);

namespace Tests;

use Shanginn\Openai\ChatCompletion\Tool\AbstractTool;
use Shanginn\Openai\ChatCompletion\Tool\OpenaiToolSchema;
use Spiral\JsonSchemaGenerator\Attribute\Field;

#[OpenaiToolSchema(
    name: 'test_tool',
    description: 'A test tool'
)]
class SampleTool extends AbstractTool
{
    public function __construct(
        #[Field(title: 'Parameter')]
        public string $parameter
    ) {}
}