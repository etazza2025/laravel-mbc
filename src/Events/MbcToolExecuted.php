<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Undergrace\Mbc\DTOs\ToolCall;
use Undergrace\Mbc\DTOs\ToolResult;

class MbcToolExecuted implements ShouldBroadcast
{
    public function __construct(
        public readonly string $sessionUuid,
        public readonly ToolCall $toolCall,
        public readonly ToolResult $toolResult,
        public readonly int $durationMs,
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
            'tool_name' => $this->toolCall->name,
            'is_error' => $this->toolResult->isError,
            'duration_ms' => $this->durationMs,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
