<?php

declare(strict_types=1);

use Undergrace\Mbc\Enums\SessionStatus;

it('has all expected cases', function () {
    $cases = SessionStatus::cases();

    expect($cases)->toHaveCount(5);
    expect(array_map(fn ($c) => $c->value, $cases))->toBe([
        'pending',
        'running',
        'completed',
        'failed',
        'max_turns',
    ]);
});

it('can be created from string value', function () {
    expect(SessionStatus::from('pending'))->toBe(SessionStatus::PENDING);
    expect(SessionStatus::from('running'))->toBe(SessionStatus::RUNNING);
    expect(SessionStatus::from('completed'))->toBe(SessionStatus::COMPLETED);
    expect(SessionStatus::from('failed'))->toBe(SessionStatus::FAILED);
    expect(SessionStatus::from('max_turns'))->toBe(SessionStatus::MAX_TURNS);
});

it('throws on invalid value', function () {
    SessionStatus::from('invalid');
})->throws(ValueError::class);
