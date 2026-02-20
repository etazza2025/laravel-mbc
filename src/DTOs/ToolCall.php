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

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'input' => $this->input,
        ];
    }
}
