<?php

declare(strict_types=1);

namespace App\Openai\ChatCompletion\Message\Assistant;

enum ToolCallTypeEnum: string
{
    case FUNCTION = 'function';
}
