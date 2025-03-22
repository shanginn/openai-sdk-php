<?php

declare(strict_types=1);

namespace App\Openai\ChatCompletion\Message;

use App\Openai\ChatCompletion\CompletionRequest\Role;

class ToolMessage implements MessageInterface
{
    /**
     * @var Role the role of the messages author, in this case "tool"
     */
    public Role $role;

    /**
     * @param string $content    the contents of the tool message
     * @param string $toolCallId tool call that this message is responding to
     */
    public function __construct(
        public string $content,
        public string $toolCallId,
    ) {
        $this->role = Role::TOOL;
    }
}
