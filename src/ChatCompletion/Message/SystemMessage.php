<?php

declare(strict_types=1);

namespace Shanginn\Openai\ChatCompletion\Message;

use Shanginn\Openai\ChatCompletion\CompletionRequest\Role;

class SystemMessage implements MessageInterface
{
    /**
     * @var Role the role of the messages author, in this case "system"
     */
    public Role $role;

    /**
     * @param string      $content the contents of the system message
     * @param string|null $name    An optional name for the participant. Provides the model information to differentiate between participants of the same role.
     */
    public function __construct(
        public string $content,
        public ?string $name = null,
    ) {
        $this->role = Role::SYSTEM;
    }
}
