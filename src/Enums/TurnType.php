<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Enums;

enum TurnType: string
{
    case USER        = 'user';
    case ASSISTANT   = 'assistant';
    case TOOL_USE    = 'tool_use';
    case TOOL_RESULT = 'tool_result';
}
