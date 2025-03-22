<?php

declare(strict_types=1);

namespace Shanginn\Openai\Exceptions;

use Shanginn\Openai\ChatCompletion\ErrorResponse;

class OpenaiErrorResponseException extends OpenaiException
{
    public function __construct(public ErrorResponse $response)
    {
        parent::__construct(
            "OpenAI API response error: {$response->message}"
        );
    }
}