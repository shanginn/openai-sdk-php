<?php

declare(strict_types=1);

namespace Shanginn\Openai\ChatCompletion\Message;

use Crell\Serde\Attributes as Serde;
use Crell\Serde\Renaming\Cases;
use Shanginn\Openai\ChatCompletion\CompletionRequest\Role;

#[Serde\ClassSettings(renameWith: Cases::snake_case, omitNullFields: true)]
final readonly class DeveloperMessage implements MessageInterface
{
    public Role $role;

    public function __construct(
        public string $content,
        public ?string $name = null,
    ) {
        $this->role = Role::DEVELOPER;
    }
}
