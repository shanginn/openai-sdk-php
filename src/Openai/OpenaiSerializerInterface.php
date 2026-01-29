<?php

declare(strict_types=1);

namespace Shanginn\Openai\Openai;

use Shanginn\Openai\Exceptions\OpenaiDeserializeException;

interface OpenaiSerializerInterface
{
    public function serialize(mixed $data): string;

    /**
     * @throws OpenaiDeserializeException
     */
    public function deserialize(mixed $serialized, string $to): object;
}