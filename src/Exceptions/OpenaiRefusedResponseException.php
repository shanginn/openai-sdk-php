<?php

declare(strict_types=1);

namespace App\Openai\Exceptions;

use App\Openai\ChatCompletion\CompletionResponse;
use InvalidArgumentException;

class OpenaiRefusedResponseException extends OpenaiException
{
    public string $refusal;

    public function __construct(public CompletionResponse $response)
    {
        $refusal = $response->choices[0]->message->refusal ?? null;
        if ($refusal === null) {
            throw new InvalidArgumentException('Response is not refused');
        }

        $this->refusal = $refusal;

        parent::__construct(
            "OpenAI API refused to response: {$refusal}",
        );
    }
}