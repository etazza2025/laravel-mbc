<?php

declare(strict_types=1);

use Undergrace\Mbc\Enums\StopReason;

it('has all expected cases', function () {
    $cases = StopReason::cases();

    expect($cases)->toHaveCount(5);
    expect(array_map(fn ($c) => $c->value, $cases))->toBe([
        'end_turn',
        'tool_use',
        'max_tokens',
        'stop_sequence',
        'pause_turn',
    ]);
});

it('can be created from string value', function () {
    expect(StopReason::from('end_turn'))->toBe(StopReason::END_TURN);
    expect(StopReason::from('tool_use'))->toBe(StopReason::TOOL_USE);
    expect(StopReason::from('max_tokens'))->toBe(StopReason::MAX_TOKENS);
});
