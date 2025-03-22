<?php

declare(strict_types=1);

namespace Shanginn\Openai\ChatCompletion\Message;

use Shanginn\Openai\ChatCompletion\CompletionRequest\Role;
use Shanginn\Openai\ChatCompletion\Message\User\ContentPartInterface;

class UserMessage implements MessageInterface
{
    /**
     * @var Role the role of the messages author, in this case "user"
     */
    public string $role;

    /**
     * @param string|array<ContentPartInterface> $content
     *                                                    The text contents of the message
     *                                                    OR
     *                                                    An array of content parts with a defined type,
     *                                                    each can be of type text or image_url when passing in images.
     *                                                    You can pass multiple images by adding multiple image_url content parts.
     *                                                    Image input is only supported when using the gpt-4o model.
     * @param string|null                        $name    An optional name for the participant. Provides the model information to differentiate between participants of the same role.
     */
    public function __construct(
        public array|string $content,
        public ?string $name = null,
    ) {
        $this->role = Role::USER->value;
    }
}
