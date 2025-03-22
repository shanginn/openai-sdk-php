<?php

declare(strict_types=1);

namespace Shanginn\Openai\ChatCompletion\CompletionRequest\JsonSchema;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class OpenaiSchema
{
    public function __construct(
        public string $name,
        public string $description,
        public bool $isStrict = true,
    ) {}
}