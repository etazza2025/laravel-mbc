<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Events;

use Undergrace\Mbc\Enums\StopReason;
use Undergrace\Mbc\Enums\TurnType;

class MbcTurnCompleted
{
    public function __construct(
        public readonly string $sessionUuid,
        public readonly int $turnNumber,
        public readonly TurnType $type,
        public readonly StopReason $stopReason,
    ) {}
}
