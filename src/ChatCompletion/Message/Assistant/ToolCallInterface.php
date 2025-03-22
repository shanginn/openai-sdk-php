<?php

declare(strict_types=1);

namespace App\Openai\ChatCompletion\Message\Assistant;

use App\Openai\Util\BackedEnumTypeMap;

#[BackedEnumTypeMap(key: 'type', map: [
    ToolCallTypeEnum::FUNCTION->value => UnknownFunctionCall::class,
])]
interface ToolCallInterface {}