<?php

declare(strict_types=1);

namespace Shanginn\Openai\Openai;

use Crell\Serde\Attributes as Serde;
use Shanginn\Openai\ChatCompletion\Message\MessageInterface;

/**
 * Internal wrapper class for deserializing arrays of messages.
 *
 * This class uses Crell\Serde's SequenceField with MessageInterface
 * to leverage the existing BackedEnumTypeMap on the interface for
 * automatic polymorphic deserialization.
 */
readonly class MessagesArray
{
    /**
     * @param array<MessageInterface> $messages
     */
    public function __construct(
        #[Serde\SequenceField(arrayType: MessageInterface::class)]
        public array $messages,
    ) {}
}
