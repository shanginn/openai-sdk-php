<?php

declare(strict_types=1);

namespace App\Openai\ChatCompletion\Message\User;

/**
 * Low or high fidelity image understanding
 * By controlling the detail parameter, which has three options,
 * low, high, or auto, you have control over how the model processes the image and generates its
 * textual understanding. By default, the model will use the auto setting which will look
 * at the image input size and decide if it should use the low or high setting.
 */
enum ImageDetailLevelEnum: string
{
    /**
     * low will enable the "low res" mode.
     * The model will receive a low-res 512px x 512px version of the image,
     * and represent the image with a budget of 85 tokens.
     * This allows the API to return faster responses and consume fewer input tokens for use cases
     * that do not require high detail.
     */
    case LOW = 'low';

    /**
     * high will enable "high res" mode,
     * which first allows the model to first see the low res image (using 85 tokens)
     * and then creates detailed crops using 170 tokens for each 512px x 512px tile.
     */
    case HIGH = 'high';
    case AUTO = 'auto';
}
