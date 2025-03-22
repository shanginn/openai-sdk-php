<?php

declare(strict_types=1);

namespace App\Openai\ChatCompletion\Message\User;

use Crell\Serde\Attributes\StaticTypeMap;

#[StaticTypeMap(key: 'type', map: [
    ContentPartTypeEnum::TEXT->value  => TextContentPart::class,
    ContentPartTypeEnum::IMAGE->value => ImageContentPart::class,
])]
interface ContentPartInterface {}