<?php

declare(strict_types=1);

namespace Shanginn\Openai\Responses;

use Shanginn\Openai\ChatCompletion\CompletionRequest\ToolInterface;
use Spiral\JsonSchemaGenerator\Generator;

final readonly class ResponseFunctionTool
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $parameters,
        public bool $strict = false,
    ) {}

    /**
     * @param class-string<ToolInterface> $tool
     */
    public static function fromTool(string $tool, bool $strict = false): self
    {
        $schema = (new Generator())->generate($tool)->jsonSerialize();

        return new self(
            name: $tool::getName(),
            description: $tool::getDescription(),
            parameters: [
                'type' => 'object',
                ...$schema,
            ],
            strict: $strict,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => 'function',
            'name' => $this->name,
            'description' => $this->description,
            'parameters' => $this->parameters,
            'strict' => $this->strict,
        ];
    }
}
