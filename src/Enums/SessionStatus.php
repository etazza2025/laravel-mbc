<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Enums;

enum SessionStatus: string
{
    case PENDING   = 'pending';
    case RUNNING   = 'running';
    case COMPLETED = 'completed';
    case FAILED    = 'failed';
    case MAX_TURNS = 'max_turns';
}
