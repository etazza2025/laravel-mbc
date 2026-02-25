<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Events;

use Illuminate\Broadcasting\PrivateChannel;
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
            new PrivateChannel("{$prefix}.sessions.{$this->sessionUuid}"),
            new PrivateChannel("{$prefix}.monitor"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'session_uuid' => $this->sessionUuid,
            'error' => 'Session failed. Check application logs for details.',
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
