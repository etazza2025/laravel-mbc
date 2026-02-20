<?php

declare(strict_types=1);

use Undergrace\Mbc\DTOs\ToolResult;

it('converts string content to API format', function () {
    $result = new ToolResult(
        toolUseId: 'toolu_123',
        toolName: 'test_tool',
        content: 'Hello world',
    );

    $apiFormat = $result->toApiFormat();

    expect($apiFormat)->toBe([
        'type' => 'tool_result',
        'tool_use_id' => 'toolu_123',
        'content' => 'Hello world',
        'is_error' => false,
    ]);
});

it('converts array content to JSON in API format', function () {
    $result = new ToolResult(
        toolUseId: 'toolu_456',
        toolName: 'data_tool',
        content: ['items' => [1, 2, 3], 'total' => 3],
    );

    $apiFormat = $result->toApiFormat();

    expect($apiFormat['type'])->toBe('tool_result');
    expect($apiFormat['tool_use_id'])->toBe('toolu_456');
    expect($apiFormat['is_error'])->toBeFalse();

    $decoded = json_decode($apiFormat['content'], true);
    expect($decoded['items'])->toBe([1, 2, 3]);
    expect($decoded['total'])->toBe(3);
});

it('marks errors correctly', function () {
    $result = new ToolResult(
        toolUseId: 'toolu_789',
        toolName: 'failing_tool',
        content: 'Something went wrong',
        isError: true,
    );

    $apiFormat = $result->toApiFormat();

    expect($apiFormat['is_error'])->toBeTrue();
    expect($apiFormat['content'])->toBe('Something went wrong');
});
