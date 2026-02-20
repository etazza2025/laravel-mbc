<?php

declare(strict_types=1);

namespace Undergrace\Mbc\DTOs;

final readonly class ToolResult
{
    public function __construct(
        public string $toolUseId,
        public string $toolName,
        public mixed $content,
        public bool $isError = false,
    ) {}

    /**
     * Convert to Anthropic API tool_result content block format.
     */
    public function toApiFormat(): array
    {
        $content = is_string($this->content)
            ? $this->content
            : json_encode($this->content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return [
            'type' => 'tool_result',
            'tool_use_id' => $this->toolUseId,
            'content' => $content,
            'is_error' => $this->isError,
        ];
    }
}
