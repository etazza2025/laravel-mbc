<?php

declare(strict_types=1);

use Mockery\MockInterface;
use Undergrace\Mbc\Contracts\MbcProviderInterface;
use Undergrace\Mbc\Core\MbcSession;
use Undergrace\Mbc\DTOs\MbcConfig;
use Undergrace\Mbc\DTOs\ProviderResponse;
use Undergrace\Mbc\DTOs\ToolCall;
use Undergrace\Mbc\Enums\SessionStatus;
use Undergrace\Mbc\Enums\StopReason;
use Undergrace\Mbc\Tools\Attributes\Tool;
use Undergrace\Mbc\Tools\Attributes\ToolParam;
use Undergrace\Mbc\Tools\BaseTool;

// ── Test tool ────────────────────────────────────────────────────

#[Tool(name: 'session_test_tool', description: 'Tool for session testing')]
class SessionTestTool extends BaseTool
{
    #[ToolParam(name: 'query', type: 'string', description: 'A query')]
    public function execute(array $input): mixed
    {
        return ['answer' => 'result for ' . ($input['query'] ?? 'unknown')];
    }
}

// ── Tests ────────────────────────────────────────────────────────

it('completes a single-turn session (end_turn)', function () {
    $mock = Mockery::mock(MbcProviderInterface::class);
    $mock->shouldReceive('complete')
        ->once()
        ->andReturn(new ProviderResponse(
            id: 'msg_single',
            stopReason: StopReason::END_TURN,
            content: [['type' => 'text', 'text' => 'Done!']],
            toolCalls: [],
            inputTokens: 100,
            outputTokens: 20,
            textContent: 'Done!',
        ));

    app()->instance(MbcProviderInterface::class, $mock);

    $session = new MbcSession('single-turn-test');
    $session->config(maxTurns: 10)->start('Do something simple');

    $result = $session->result();

    expect($result->status)->toBe(SessionStatus::COMPLETED);
    expect($result->totalTurns)->toBe(1);
    expect($result->finalMessage)->toBe('Done!');
    expect($result->totalInputTokens)->toBe(100);
    expect($result->totalOutputTokens)->toBe(20);
});

it('executes a multi-turn session with tool use', function () {
    $mock = Mockery::mock(MbcProviderInterface::class);

    // Turn 1: AI wants to use a tool
    $mock->shouldReceive('complete')
        ->once()
        ->andReturn(new ProviderResponse(
            id: 'msg_1',
            stopReason: StopReason::TOOL_USE,
            content: [
                ['type' => 'text', 'text' => 'Let me search...'],
                ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'session_test_tool', 'input' => ['query' => 'hello']],
            ],
            toolCalls: [new ToolCall('toolu_1', 'session_test_tool', ['query' => 'hello'])],
            inputTokens: 150,
            outputTokens: 60,
            textContent: 'Let me search...',
        ));

    // Turn 2: AI finishes after getting tool result
    $mock->shouldReceive('complete')
        ->once()
        ->andReturn(new ProviderResponse(
            id: 'msg_2',
            stopReason: StopReason::END_TURN,
            content: [['type' => 'text', 'text' => 'Based on the results, here is your answer.']],
            toolCalls: [],
            inputTokens: 250,
            outputTokens: 40,
            textContent: 'Based on the results, here is your answer.',
        ));

    app()->instance(MbcProviderInterface::class, $mock);

    $session = new MbcSession('multi-turn-test');
    $session->tools([SessionTestTool::class])
        ->config(maxTurns: 10)
        ->start('Search for hello');

    $result = $session->result();

    expect($result->status)->toBe(SessionStatus::COMPLETED);
    expect($result->totalTurns)->toBe(2);
    expect($result->finalMessage)->toBe('Based on the results, here is your answer.');
    expect($result->totalInputTokens)->toBe(400);
    expect($result->totalOutputTokens)->toBe(100);
});

it('stops at max turns', function () {
    $mock = Mockery::mock(MbcProviderInterface::class);

    // Always return tool_use so the loop never ends naturally
    $mock->shouldReceive('complete')
        ->andReturn(new ProviderResponse(
            id: 'msg_loop',
            stopReason: StopReason::TOOL_USE,
            content: [
                ['type' => 'tool_use', 'id' => 'toolu_loop', 'name' => 'session_test_tool', 'input' => ['query' => 'loop']],
            ],
            toolCalls: [new ToolCall('toolu_loop', 'session_test_tool', ['query' => 'loop'])],
            inputTokens: 50,
            outputTokens: 30,
            textContent: null,
        ));

    app()->instance(MbcProviderInterface::class, $mock);

    $session = new MbcSession('max-turns-test');
    $session->tools([SessionTestTool::class])
        ->config(maxTurns: 3)
        ->start('Loop forever');

    $result = $session->result();

    expect($result->status)->toBe(SessionStatus::MAX_TURNS);
    expect($result->totalTurns)->toBe(3);
});

it('uses fluent builder API', function () {
    $session = new MbcSession('builder-test');

    $returned = $session
        ->systemPrompt('You are a test agent.')
        ->tools([SessionTestTool::class])
        ->context(['key' => 'value'])
        ->config(maxTurns: 5, temperature: 0.5)
        ->middleware([]);

    expect($returned)->toBeInstanceOf(MbcSession::class);
    expect($returned->uuid())->toBeString();
});

it('can be serialized and deserialized for jobs', function () {
    $session = new MbcSession('serialize-test');
    $session->systemPrompt('Test prompt')
        ->tools([SessionTestTool::class])
        ->context(['business' => 'test'])
        ->config(maxTurns: 20, model: 'claude-opus-4-20250514')
        ->middleware([]);

    $serialized = $session->toSerializable();

    expect($serialized)->toBeArray();
    expect($serialized['name'])->toBe('serialize-test');
    expect($serialized['system_prompt'])->toBe('Test prompt');
    expect($serialized['tool_classes'])->toContain(SessionTestTool::class);
    expect($serialized['context'])->toBe(['business' => 'test']);

    $restored = MbcSession::fromSerializable($serialized);
    expect($restored)->toBeInstanceOf(MbcSession::class);
    expect($restored->uuid())->toBeString();
});
