<?php

declare(strict_types=1);

namespace App\Openai\ChatCompletion\Message\User;

enum ContentPartTypeEnum: string
{
    case TEXT  = 'text';
    case IMAGE = 'image_url';
}
