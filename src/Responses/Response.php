<?php

declare(strict_types=1);

namespace Shanginn\Openai\Responses;

use JsonException;

final readonly class Response
{
    /**
     * @param array<array<string, mixed>> $output
     * @param array<string, mixed>|null   $usage
     * @param array<string, mixed>|null   $incompleteDetails
     * @param array<string, mixed>        $raw
     */
    public function __construct(
        public string $id,
        public string $model,
        public ?string $status,
        public array $output,
        public ?array $usage,
        public ?array $incompleteDetails,
        public array $raw,
    ) {}

    /**
     * @throws JsonException
     */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return new self(
            id: (string) ($data['id'] ?? ''),
            model: (string) ($data['model'] ?? ''),
            status: isset($data['status']) ? (string) $data['status'] : null,
            output: is_array($data['output'] ?? null) ? $data['output'] : [],
            usage: is_array($data['usage'] ?? null) ? $data['usage'] : null,
            incompleteDetails: is_array($data['incomplete_details'] ?? null)
                ? $data['incomplete_details']
                : null,
            raw: $data,
        );
    }

    public function outputText(): string
    {
        $text = [];

        foreach ($this->output as $item) {
            if (($item['type'] ?? null) !== 'message') {
                continue;
            }

            foreach ($item['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'output_text' && isset($content['text'])) {
                    $text[] = (string) $content['text'];
                }
            }
        }

        return implode('', $text);
    }

    public function refusal(): ?string
    {
        foreach ($this->output as $item) {
            foreach ($item['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'refusal') {
                    return isset($content['refusal']) ? (string) $content['refusal'] : '';
                }
            }
        }

        return null;
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function functionCalls(): array
    {
        return array_values(array_filter(
            $this->output,
            static fn(array $item): bool => ($item['type'] ?? null) === 'function_call',
        ));
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function reasoningItems(): array
    {
        return array_values(array_filter(
            $this->output,
            static fn(array $item): bool => ($item['type'] ?? null) === 'reasoning',
        ));
    }
}
