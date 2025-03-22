<?php

declare(strict_types=1);

namespace Shanginn\Openai\ChatCompletion\CompletionRequest;

enum ResponseFormatEnum: string
{
    case TEXT        = 'text';
    case JSON        = 'json_object';
    case JSON_SCHEMA = 'json_schema';
}
