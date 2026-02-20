<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Enums;

enum StopReason: string
{
    case END_TURN      = 'end_turn';
    case TOOL_USE      = 'tool_use';
    case MAX_TOKENS    = 'max_tokens';
    case STOP_SEQUENCE = 'stop_sequence';
    case PAUSE_TURN    = 'pause_turn';
}
