<?php

declare(strict_types=1);

namespace Shanginn\Openai\ChatCompletion\Message\Assistant;

enum ToolCallTypeEnum: string
{
    case FUNCTION = 'function';
}
