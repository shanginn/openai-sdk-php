<?php

declare(strict_types=1);

namespace App\Openai\Exceptions;

use App\Openai\ChatCompletion\ErrorResponse;

class OpenaiErrorResponseException extends OpenaiException
{
    public function __construct(public ErrorResponse $response)
    {
        parent::__construct(
            "OpenAI API response error: {$response->message}"
        );
    }
}