<?php

declare(strict_types=1);

namespace Undergrace\Mbc\DTOs;

use Undergrace\Mbc\Enums\StopReason;

final readonly class ProviderResponse
{
    /**
     * @param string $id Response ID from the API
     * @param StopReason $stopReason Why the model stopped generating
     * @param array $content Raw content blocks array from the API
     * @param ToolCall[] $toolCalls Parsed tool calls from tool_use blocks
     * @param int $inputTokens Tokens consumed in the input
     * @param int $outputTokens Tokens generated in the output
     * @param string|null $textContent Concatenated text from text blocks
     */
    public function __construct(
        public string $id,
        public StopReason $stopReason,
        public array $content,
        public array $toolCalls,
        public int $inputTokens,
        public int $outputTokens,
        public ?string $textContent,
    ) {}
}
