<?php

declare(strict_types=1);

namespace App\Openai\ChatCompletion\Tool;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class OpenaiToolSchema
{
    public function __construct(
        public string $name,
        public string $description,
    ) {}
}