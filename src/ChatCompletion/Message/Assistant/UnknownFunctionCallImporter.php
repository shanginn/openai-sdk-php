<?php

declare(strict_types=1);

namespace Shanginn\Openai\ChatCompletion\Message\Assistant;

use Crell\Serde\Attributes\Field;
use Crell\Serde\Deserializer;
use Crell\Serde\PropertyHandler\Importer;

class UnknownFunctionCallImporter implements Importer
{
    public function importValue(Deserializer $deserializer, Field $field, mixed $source): mixed
    {
        return new UnknownFunctionCall(
            id: $source[$field->serializedName]['id'],
            name: $source[$field->serializedName]['function']['name'],
            arguments: $source[$field->serializedName]['function']['arguments'],
        );
    }

    public function canImport(Field $field, string $format): bool
    {
        return $field->phpType === UnknownFunctionCall::class;
    }
}