<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Events;

use Undergrace\Mbc\DTOs\SessionResult;

class MbcSessionCompleted
{
    public function __construct(
        public readonly string $sessionUuid,
        public readonly SessionResult $result,
    ) {}
}
