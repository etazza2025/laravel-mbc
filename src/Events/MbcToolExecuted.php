<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Events;

use Undergrace\Mbc\DTOs\ToolCall;
use Undergrace\Mbc\DTOs\ToolResult;

class MbcToolExecuted
{
    public function __construct(
        public readonly ToolCall $toolCall,
        public readonly ToolResult $toolResult,
        public readonly int $durationMs,
    ) {}
}
