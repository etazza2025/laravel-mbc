<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Undergrace\Mbc\DTOs\SessionResult;

class MbcSessionCompleted implements ShouldBroadcast
{
    public function __construct(
        public readonly string $sessionUuid,
        public readonly SessionResult $result,
    ) {}

    public function broadcastOn(): array
    {
        if (! config('mbc.broadcasting.enabled', false)) {
            return [];
        }

        $prefix = config('mbc.broadcasting.channel_prefix', 'mbc');

        return [
            new Channel("{$prefix}.sessions.{$this->sessionUuid}"),
            new Channel("{$prefix}.monitor"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'session_uuid' => $this->sessionUuid,
            'status' => $this->result->status->value,
            'final_message' => $this->result->finalMessage,
            'total_turns' => $this->result->totalTurns,
            'total_input_tokens' => $this->result->totalInputTokens,
            'total_output_tokens' => $this->result->totalOutputTokens,
            'estimated_cost_usd' => $this->result->estimatedCostUsd,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
