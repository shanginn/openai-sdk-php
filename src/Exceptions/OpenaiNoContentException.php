<?php

declare(strict_types=1);

namespace App\Openai\Exceptions;

use App\Openai\ChatCompletion\CompletionResponse;

class OpenaiNoContentException extends OpenaiInvalidResponseException
{
    public function __construct(CompletionResponse $response)
    {
        parent::__construct(
            $response,
            'OpenAI API response has no content',
        );
    }
}