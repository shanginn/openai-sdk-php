<?php

declare(strict_types=1);

namespace Shanginn\Openai\Openai;

use Shanginn\Openai\ChatCompletion\CompletionRequest\ToolInterface;
use Shanginn\Openai\Exceptions\OpenaiDeserializeException;

interface OpenaiSerializerInterface
{
    public function serialize(mixed $data): string;

    /**
     * @param array<class-string<ToolInterface>>|null $tools
     *
     * @throws OpenaiDeserializeException
     */
    public function deserialize(mixed $serialized, string $to, ?array $tools = null): object|array;
}