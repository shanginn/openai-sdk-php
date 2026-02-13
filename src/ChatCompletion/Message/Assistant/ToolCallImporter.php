<?php

declare(strict_types=1);

namespace Shanginn\Openai\ChatCompletion\Message\Assistant;

use Crell\Serde\SerdeCommon;
use Crell\Serde\Attributes\Field;
use Crell\Serde\Deserializer;
use Crell\Serde\PropertyHandler\Importer;
use Shanginn\Openai\ChatCompletion\CompletionRequest\ToolInterface;
use Throwable;

class ToolCallImporter implements Importer
{
    private SerdeCommon $toolDeserializer;

    /**
     * @var array<string, class-string<ToolInterface>>
     */
    private array $toolsMap = [];

    public function __construct()
    {
        // Create a deserializer for tool arguments
        // This is separate from the main deserializer to avoid state conflicts
        $this->toolDeserializer = new SerdeCommon();
    }

    /**
     * Set the tools map for the next deserialization.
     * 
     * @param array<class-string<ToolInterface>> $tools
     */
    public function setTools(array $tools): void
    {
        $this->toolsMap = array_merge(...array_map(
            fn (string $tool) => [$tool::getName() => $tool],
            $tools
        ));
    }

    public function importValue(Deserializer $deserializer, Field $field, mixed $source): mixed
    {
        $data = $source[$field->serializedName];
        $functionName = $data['function']['name'];
        $arguments = $data['function']['arguments'];
        $id = $data['id'];

        if (!isset($this->toolsMap[$functionName])) {
            return new UnknownFunctionCall(
                id: $id,
                name: $functionName,
                arguments: $arguments,
            );
        }

        $tool = $this->toolsMap[$functionName];

        try {
            $toolInput = $this->toolDeserializer->deserialize(
                serialized: $arguments,
                from: 'json',
                to: $tool,
            );

            return new KnownFunctionCall(
                id: $id,
                tool: $tool,
                arguments: $toolInput,
            );
        } catch (Throwable $e) {
            return new UnknownFunctionCall(
                id: $id,
                name: $functionName,
                arguments: $arguments,
            );
        }
    }

    public function canImport(Field $field, string $format): bool
    {
        return $field->phpType === ToolCallInterface::class
            || $field->phpType === UnknownFunctionCall::class
            || $field->phpType === KnownFunctionCall::class;
    }
}
