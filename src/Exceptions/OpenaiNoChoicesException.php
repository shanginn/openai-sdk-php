<?php

declare(strict_types=1);

namespace Shanginn\Openai\Exceptions;

use Shanginn\Openai\ChatCompletion\CompletionResponse;

class OpenaiNoChoicesException extends OpenaiInvalidResponseException
{
    public function __construct(CompletionResponse $response)
    {
        parent::__construct(
            $response,
            'OpenAI API response has no suitable choices'
        );
    }
}