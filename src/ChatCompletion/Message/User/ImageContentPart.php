<?php

declare(strict_types=1);

namespace App\Openai\ChatCompletion\Message\User;

final readonly class ImageContentPart implements ContentPartInterface
{
    public ContentPartTypeEnum $type;

    /**
     * @param string                    $url    either a URL of the image or the base64 encoded image data
     * @param ImageDetailLevelEnum|null $detail Specifies the detail level of the image. @see https://platform.openai.com/docs/guides/vision/low-or-high-fidelity-image-understanding
     */
    public function __construct(
        public string $url,
        public ?ImageDetailLevelEnum $detail = null,
    ) {
        $this->type = ContentPartTypeEnum::IMAGE;
    }
}