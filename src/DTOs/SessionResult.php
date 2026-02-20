<?php

declare(strict_types=1);

namespace Undergrace\Mbc\DTOs;

use Undergrace\Mbc\Enums\SessionStatus;

final readonly class SessionResult
{
    public function __construct(
        public string $uuid,
        public SessionStatus $status,
        public ?string $finalMessage,
        public int $totalTurns,
        public int $totalInputTokens,
        public int $totalOutputTokens,
        public float $estimatedCostUsd,
        public array $metadata,
    ) {}

    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'final_message' => $this->finalMessage,
            'total_turns' => $this->totalTurns,
            'total_input_tokens' => $this->totalInputTokens,
            'total_output_tokens' => $this->totalOutputTokens,
            'estimated_cost_usd' => $this->estimatedCostUsd,
            'metadata' => $this->metadata,
        ];
    }
}
