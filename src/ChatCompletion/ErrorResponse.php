<?php

declare(strict_types=1);

namespace Shanginn\Openai\ChatCompletion;

/**
 * Represents the response from an API for a message request.
 */
final class ErrorResponse
{
    public function __construct(
        public ?string $message,
        public ?string $type,
        public ?string $param,
        public null|int|string $code,
    ) {}
}
