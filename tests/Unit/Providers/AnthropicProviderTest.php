<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Undergrace\Mbc\DTOs\MbcConfig;
use Undergrace\Mbc\DTOs\ToolDefinition;
use Undergrace\Mbc\Enums\StopReason;
use Undergrace\Mbc\Providers\AnthropicProvider;

beforeEach(function () {
    config()->set('mbc.providers.anthropic.api_key', 'test-key-123');
    config()->set('mbc.providers.anthropic.base_url', 'https://api.anthropic.com/v1');
});

it('sends correct payload to Anthropic API', function () {
    Http::fake([
        'api.anthropic.com/v1/messages' => Http::response([
            'id' => 'msg_test_001',
            'type' => 'message',
            'role' => 'assistant',
            'stop_reason' => 'end_turn',
            'content' => [
                ['type' => 'text', 'text' => 'Hello!'],
            ],
            'usage' => ['input_tokens' => 15, 'output_tokens' => 5],
        ]),
    ]);

    $provider = new AnthropicProvider();
    $response = $provider->complete(
        system: 'You are a test assistant.',
        messages: [['role' => 'user', 'content' => 'Hi']],
        tools: [],
        config: new MbcConfig(),
    );

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'api.anthropic.com/v1/messages')
            && $request->hasHeader('x-api-key', 'test-key-123')
            && $request->hasHeader('anthropic-version', '2023-06-01')
            && $request['model'] === 'claude-sonnet-4-5-20250929'
            && $request['system'] === 'You are a test assistant.'
            && $request['messages'][0]['role'] === 'user';
    });

    expect($response->id)->toBe('msg_test_001');
    expect($response->stopReason)->toBe(StopReason::END_TURN);
    expect($response->textContent)->toBe('Hello!');
    expect($response->inputTokens)->toBe(15);
    expect($response->outputTokens)->toBe(5);
    expect($response->toolCalls)->toBe([]);
});

it('parses tool_use responses correctly', function () {
    Http::fake([
        'api.anthropic.com/v1/messages' => Http::response([
            'id' => 'msg_test_002',
            'type' => 'message',
            'role' => 'assistant',
            'stop_reason' => 'tool_use',
            'content' => [
                ['type' => 'text', 'text' => 'Let me check...'],
                [
                    'type' => 'tool_use',
                    'id' => 'toolu_abc123',
                    'name' => 'get_weather',
                    'input' => ['location' => 'Madrid'],
                ],
            ],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
        ]),
    ]);

    $provider = new AnthropicProvider();
    $response = $provider->complete(
        system: 'Test',
        messages: [['role' => 'user', 'content' => 'Weather?']],
        tools: [
            new ToolDefinition('get_weather', 'Gets weather', [
                'type' => 'object',
                'properties' => ['location' => ['type' => 'string']],
                'required' => ['location'],
            ]),
        ],
        config: new MbcConfig(),
    );

    expect($response->stopReason)->toBe(StopReason::TOOL_USE);
    expect($response->textContent)->toBe('Let me check...');
    expect($response->toolCalls)->toHaveCount(1);
    expect($response->toolCalls[0]->id)->toBe('toolu_abc123');
    expect($response->toolCalls[0]->name)->toBe('get_weather');
    expect($response->toolCalls[0]->input)->toBe(['location' => 'Madrid']);
});

it('includes tools in the request payload', function () {
    Http::fake([
        'api.anthropic.com/v1/messages' => Http::response([
            'id' => 'msg_003',
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => 'OK']],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ]),
    ]);

    $provider = new AnthropicProvider();
    $provider->complete(
        system: 'Test',
        messages: [['role' => 'user', 'content' => 'Hi']],
        tools: [
            new ToolDefinition('my_tool', 'A tool', [
                'type' => 'object',
                'properties' => ['param' => ['type' => 'string']],
                'required' => ['param'],
            ]),
        ],
        config: new MbcConfig(),
    );

    Http::assertSent(function ($request) {
        return isset($request['tools'])
            && count($request['tools']) === 1
            && $request['tools'][0]['name'] === 'my_tool';
    });
});
