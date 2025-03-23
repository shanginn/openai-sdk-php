<?php

declare(strict_types=1);

namespace Shanginn\Openai\ChatCompletion;

use Crell\Serde\Attributes as Serde;
use Crell\Serde\Renaming\Cases;

/**
 * Represents the response from an API for a message request.
 */
#[Serde\ClassSettings(renameWith: Cases::snake_case, omitNullFields: true)]
final class ErrorResponse
{
    public function __construct(
        public ?string $message,
        public ?string $type,
        public ?string $param,
        public null|int|string $code,
    ) {}
}