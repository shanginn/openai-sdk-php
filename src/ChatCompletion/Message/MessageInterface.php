<?php

declare(strict_types=1);

namespace App\Openai\ChatCompletion\Message;

use App\Openai\ChatCompletion\CompletionRequest\Role;
use App\Openai\Util\BackedEnumTypeMap;

#[BackedEnumTypeMap(key: 'role', map: [
    Role::USER->value      => UserMessage::class,
    Role::ASSISTANT->value => AssistantMessage::class,
    Role::SYSTEM->value    => SystemMessage::class,
    Role::TOOL->value      => ToolMessage::class,
])]
interface MessageInterface {}