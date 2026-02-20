<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Events;

class MbcSessionFailed
{
    public function __construct(
        public readonly string $sessionUuid,
        public readonly string $error,
    ) {}
}
