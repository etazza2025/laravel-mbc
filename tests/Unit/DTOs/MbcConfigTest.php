<?php

declare(strict_types=1);

use Undergrace\Mbc\DTOs\MbcConfig;

it('has sensible defaults', function () {
    $config = new MbcConfig();

    expect($config->maxTurns)->toBe(30);
    expect($config->maxTokensPerTurn)->toBe(4096);
    expect($config->model)->toBe('claude-sonnet-4-5-20250929');
    expect($config->temperature)->toBe(1.0);
    expect($config->timeoutSeconds)->toBe(120);
    expect($config->retryTimes)->toBe(3);
    expect($config->retrySleepMs)->toBe(1000);
});

it('can be created with custom values', function () {
    $config = new MbcConfig(
        maxTurns: 50,
        maxTokensPerTurn: 8192,
        model: 'claude-opus-4-20250514',
        temperature: 0.5,
    );

    expect($config->maxTurns)->toBe(50);
    expect($config->maxTokensPerTurn)->toBe(8192);
    expect($config->model)->toBe('claude-opus-4-20250514');
    expect($config->temperature)->toBe(0.5);
});

it('can be created from array', function () {
    $config = MbcConfig::fromArray([
        'max_turns' => 10,
        'model' => 'test-model',
        'temperature' => 0.7,
    ]);

    expect($config->maxTurns)->toBe(10);
    expect($config->model)->toBe('test-model');
    expect($config->temperature)->toBe(0.7);
    // Defaults for unset values
    expect($config->maxTokensPerTurn)->toBe(4096);
});

it('can be serialized to array', function () {
    $config = new MbcConfig(maxTurns: 20, temperature: 0.5);
    $array = $config->toArray();

    expect($array)->toBeArray();
    expect($array['max_turns'])->toBe(20);
    expect($array['temperature'])->toBe(0.5);
});

it('is immutable', function () {
    $config = new MbcConfig();

    expect(fn () => $config->maxTurns = 100)->toThrow(Error::class);
});
