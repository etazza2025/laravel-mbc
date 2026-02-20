<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Events;

class MbcSessionStarted
{
    public function __construct(
        public readonly string $sessionUuid,
        public readonly string $sessionName,
    ) {}
}
