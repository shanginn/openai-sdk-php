<?php

declare(strict_types=1);

namespace Shanginn\Openai\Exceptions;

final class OpenaiTransportException extends OpenaiException
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?string $responseBody = null,
    ) {
        parent::__construct($message);
    }
}
