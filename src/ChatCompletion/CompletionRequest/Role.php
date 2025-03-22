<?php

declare(strict_types=1);

namespace App\Openai\ChatCompletion\CompletionRequest;

enum Role: string
{
    case SYSTEM    = 'system';
    case USER      = 'user';
    case ASSISTANT = 'assistant';
    case TOOL      = 'tool';
}
