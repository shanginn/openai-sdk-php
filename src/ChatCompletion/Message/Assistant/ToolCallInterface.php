<?php

declare(strict_types=1);

namespace Shanginn\Openai\ChatCompletion\Message\Assistant;

use Shanginn\Openai\Util\BackedEnumTypeMap;

#[BackedEnumTypeMap(key: 'type', map: [
    ToolCallTypeEnum::FUNCTION->value => UnknownFunctionCall::class,
])]
interface ToolCallInterface {}