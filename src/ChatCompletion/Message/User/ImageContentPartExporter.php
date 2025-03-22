<?php

declare(strict_types=1);

namespace Shanginn\Openai\ChatCompletion\Message\User;

use Crell\Serde\Attributes\Field;
use Crell\Serde\Deserializer;
use Crell\Serde\PropertyHandler\Exporter;
use Crell\Serde\PropertyHandler\Importer;
use Crell\Serde\Serializer;

/** @deprecated  */
class ImageContentPartExporter implements Exporter, Importer
{
    public function exportValue(Serializer $serializer, Field $field, mixed $value, mixed $runningValue): mixed
    {
        assert($value instanceof ImageContentPart);

        $imageUrl = [
            'url' => $value->url,
        ];

        if ($value->detail !== null) {
            $imageUrl['detail'] = $value->detail;
        }

        return [
            'type'      => $value->type,
            'image_url' => $imageUrl,
        ];
    }

    public function canExport(Field $field, mixed $value, string $format): bool
    {
        return $value instanceof ImageContentPart;
    }

    public function importValue(Deserializer $deserializer, Field $field, mixed $source): mixed
    {
        return new ImageContentPart(
            url: $source['image_url']['url'],
            detail: $source['image_url']['detail'] ?? null,
        );
    }

    public function canImport(Field $field, string $format): bool
    {
        return $field->phpType === ImageContentPart::class;
    }
}