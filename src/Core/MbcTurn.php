<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Core;

use Undergrace\Mbc\DTOs\ToolCall;
use Undergrace\Mbc\DTOs\ToolResult;
use Undergrace\Mbc\Enums\StopReason;
use Undergrace\Mbc\Enums\TurnType;

/**
 * Value object representing a single turn in an MBC session.
 */
final readonly class MbcTurn
{
    /**
     * @param int $turnNumber Sequential turn number within the session
     * @param TurnType $type Type of turn (assistant, tool_result, etc.)
     * @param array $content Raw content blocks
     * @param ToolCall[] $toolCalls Tool calls made by the AI in this turn
     * @param ToolResult[] $toolResults Results of tool executions
     * @param int $inputTokens Tokens consumed in input
     * @param int $outputTokens Tokens generated in output
     * @param StopReason|null $stopReason Why the AI stopped
     * @param int $durationMs Time taken for this turn in milliseconds
     */
    public function __construct(
        public int $turnNumber,
        public TurnType $type,
        public array $content,
        public array $toolCalls = [],
        public array $toolResults = [],
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public ?StopReason $stopReason = null,
        public int $durationMs = 0,
    ) {}

    public function toArray(): array
    {
        return [
            'turn_number' => $this->turnNumber,
            'type' => $this->type->value,
            'content' => $this->content,
            'tool_calls' => array_map(fn (ToolCall $tc) => $tc->toArray(), $this->toolCalls),
            'tool_results' => array_map(fn (ToolResult $tr) => $tr->toApiFormat(), $this->toolResults),
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'stop_reason' => $this->stopReason?->value,
            'duration_ms' => $this->durationMs,
        ];
    }
}
