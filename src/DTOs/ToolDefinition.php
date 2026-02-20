<?php

declare(strict_types=1);

namespace Undergrace\Mbc\DTOs;

final readonly class ToolDefinition
{
    public function __construct(
        public string $name,
        public string $description,
        public array $inputSchema,
    ) {}

    /**
     * Convert to Anthropic API tool definition format.
     */
    public function toApiFormat(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'input_schema' => $this->inputSchema,
        ];
    }
}
