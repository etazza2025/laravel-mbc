<?php

declare(strict_types=1);

use Undergrace\Mbc\DTOs\ToolCall;

it('can be created from Anthropic API block', function () {
    $block = [
        'type' => 'tool_use',
        'id' => 'toolu_01ABC123',
        'name' => 'get_weather',
        'input' => ['location' => 'San Francisco'],
    ];

    $toolCall = ToolCall::fromApiBlock($block);

    expect($toolCall->id)->toBe('toolu_01ABC123');
    expect($toolCall->name)->toBe('get_weather');
    expect($toolCall->input)->toBe(['location' => 'San Francisco']);
});

it('handles missing input gracefully', function () {
    $block = [
        'type' => 'tool_use',
        'id' => 'toolu_02DEF456',
        'name' => 'list_items',
    ];

    $toolCall = ToolCall::fromApiBlock($block);

    expect($toolCall->input)->toBe([]);
});

it('can be serialized to array', function () {
    $toolCall = new ToolCall(
        id: 'toolu_test',
        name: 'my_tool',
        input: ['param' => 'value'],
    );

    $array = $toolCall->toArray();

    expect($array)->toBe([
        'id' => 'toolu_test',
        'name' => 'my_tool',
        'input' => ['param' => 'value'],
    ]);
});
