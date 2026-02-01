<?php

declare(strict_types=1);

namespace Shanginn\Openai\ChatCompletion\Message;

use Shanginn\Openai\ChatCompletion\CompletionRequest\Role;
use Shanginn\Openai\ChatCompletion\Message\Assistant\ToolCallInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class AssistantMessageDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): AssistantMessage
    {
        // Create object without calling constructor (bypass validation)
        $message = (new \ReflectionClass(AssistantMessage::class))->newInstanceWithoutConstructor();
        
        // Set properties directly
        $message->role = Role::ASSISTANT;
        $message->content = $data['content'] ?? null;
        $message->name = $data['name'] ?? null;
        $message->refusal = $data['refusal'] ?? null;
        
        // Handle tool_calls if present
        if (isset($data['tool_calls'])) {
            $toolCalls = [];
            foreach ($data['tool_calls'] as $toolCallData) {
                $toolCalls[] = $this->denormalizer->denormalize(
                    $toolCallData,
                    ToolCallInterface::class,
                    $format,
                    $context
                );
            }
            $message->toolCalls = $toolCalls;
        } else {
            $message->toolCalls = null;
        }
        
        return $message;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $type === AssistantMessage::class;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            AssistantMessage::class => true,
        ];
    }
}
