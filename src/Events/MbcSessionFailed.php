<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class MbcSessionFailed implements ShouldBroadcast
{
    public function __construct(
        public readonly string $sessionUuid,
        public readonly string $error,
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
            'error' => $this->error,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
