<?php

declare(strict_types=1);

namespace Shanginn\Openai\Provider;

enum Provider: string
{
    case OPENAI = 'openai';
    case DEEPSEEK = 'deepseek';
    case QWEN = 'qwen';
    case XAI = 'xai';
    case CUSTOM = 'custom';
}
