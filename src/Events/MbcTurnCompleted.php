<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Undergrace\Mbc\Enums\StopReason;
use Undergrace\Mbc\Enums\TurnType;

class MbcTurnCompleted implements ShouldBroadcast
{
    public function __construct(
        public readonly string $sessionUuid,
        public readonly int $turnNumber,
        public readonly TurnType $type,
        public readonly StopReason $stopReason,
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
            'turn_number' => $this->turnNumber,
            'type' => $this->type->value,
            'stop_reason' => $this->stopReason->value,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
