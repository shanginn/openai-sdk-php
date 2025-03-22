<?php

declare(strict_types=1);

namespace App\Openai\Util;

use Attribute;
use BackedEnum;
use Crell\Serde\Attributes\StaticTypeMap;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class BackedEnumTypeMap extends StaticTypeMap
{
    public function findClass(BackedEnum|string $id): ?string
    {
        if ($id instanceof BackedEnum) {
            $id = $id->value;
        }

        return parent::findClass($id);
    }
}