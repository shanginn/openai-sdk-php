<?php

declare(strict_types=1);

namespace Shanginn\Openai\Exceptions;

class OpenaiDeserializeException extends OpenaiException
{
    public function __construct(
        public mixed $serialized = null,
        public string $to = '',
        ?\Throwable $previous = null
    ) {
        $stringableData = is_string($serialized) ? $serialized : var_export($serialized, true);

        parent::__construct(
            sprintf('Failed to deserialize data to %s. Data: %s', $to, $stringableData),
            previous: $previous
        );
    }
}