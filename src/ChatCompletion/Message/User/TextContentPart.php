<?php

declare(strict_types=1);

namespace Shanginn\Openai\ChatCompletion\Message\User;

final readonly class TextContentPart implements ContentPartInterface
{
    public ContentPartTypeEnum $type;

    public function __construct(
        public string $text,
    ) {
        $this->type = ContentPartTypeEnum::TEXT;
    }
}