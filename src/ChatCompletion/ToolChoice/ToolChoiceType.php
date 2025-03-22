<?php

declare(strict_types=1);

namespace Shanginn\Openai\ChatCompletion\ToolChoice;

enum ToolChoiceType: string
{
    /** none means the model will not call any tool and instead generates a message. */
    case NONE = 'none';

    /** auto means the model can pick between generating a message or calling one or more tools. */
    case AUTO = 'auto';

    /** required means the model must call one or more tools. */
    case REQUIRED = 'required';
}