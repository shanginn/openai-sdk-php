<?php

declare(strict_types=1);

namespace App\Openai\ChatCompletion;

use App\Openai\ChatCompletion\CompletionResponse\Choice;
use App\Openai\ChatCompletion\CompletionResponse\Usage;
use Crell\Serde\Attributes as Serde;
use Crell\Serde\Renaming\Cases;

/**
 * Represents the response from an API for a message request.
 */
#[Serde\ClassSettings(renameWith: Cases::snake_case, omitNullFields: true)]
final class CompletionResponse
{
    /**
     * @param string        $id                Unique identifier for the response object
     * @param array<Choice> $choices           A list of chat completion choices
     * @param string        $model             The model used for the chat completion
     * @param Usage         $usage             Information about billing and rate-limit usage based on token counts
     * @param string        $object            The object type, which is always chat.completion
     * @param int           $created           The Unix timestamp (in seconds) of when the chat completion was created
     * @param string|null   $serviceTier       The service tier used for processing the request
     * @param string|null   $systemFingerprint The backend configuration fingerprint for determinism
     */
    public function __construct(
        public string $id,
        #[Serde\SequenceField(arrayType: Choice::class)]
        public array $choices,
        public string $model,
        public Usage $usage,
        public string $object,
        public int $created,
        public ?string $serviceTier = null,
        public ?string $systemFingerprint = null,
    ) {}
}