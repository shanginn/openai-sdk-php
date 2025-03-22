<?php

declare(strict_types=1);

namespace App\Openai\Exceptions;

use App\Openai\ChatCompletion\CompletionResponse;

class OpenaiInvalidResponseException extends OpenaiException
{
    public function __construct(
        public CompletionResponse $response,
        string $message = 'OpenAI API response is invalid'
    ) {
        parent::__construct($message);
    }
}