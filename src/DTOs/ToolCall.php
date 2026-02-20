<?php

declare(strict_types=1);

namespace Undergrace\Mbc\DTOs;

final readonly class ToolCall
{
    public function __construct(
        public string $id,
        public string $name,
        public array $input,
    ) {}

    /**
     * Create from an Anthropic API tool_use content block.
     */
    public static function fromApiBlock(array $block): self
    {
        return new self(
            id: $block['id'],
            name: $block['name'],
            input: $block['input'] ?? [],
        );
    }

    /**
     * Create from an OpenAI-compatible tool_calls block.
     * Works with OpenAI, OpenRouter, and other OpenAI-compatible APIs.
     */
    public static function fromOpenAiBlock(array $block): self
    {
        $function = $block['function'] ?? [];

        return new self(
            id: $block['id'],
            name: $function['name'] ?? '',
            input: json_decode($function['arguments'] ?? '{}', true) ?: [],
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'input' => $this->input,
        ];
    }
}
