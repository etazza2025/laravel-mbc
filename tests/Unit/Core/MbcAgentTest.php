<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Undergrace\Mbc\Core\MbcAgent;
use Undergrace\Mbc\Core\MbcToolkit;
use Undergrace\Mbc\DTOs\ToolCall;
use Undergrace\Mbc\Tools\Attributes\Tool;
use Undergrace\Mbc\Tools\Attributes\ToolParam;
use Undergrace\Mbc\Tools\BaseTool;

// ── Test tools ───────────────────────────────────────────────────

#[Tool(name: 'agent_success_tool', description: 'A tool that succeeds')]
class AgentSuccessTool extends BaseTool
{
    #[ToolParam(name: 'value', type: 'string', description: 'A value')]
    public function execute(array $input): mixed
    {
        return ['received' => $input['value'] ?? null];
    }
}

#[Tool(name: 'agent_failing_tool', description: 'A tool that throws')]
class AgentFailingTool extends BaseTool
{
    public function execute(array $input): mixed
    {
        throw new RuntimeException('Tool exploded');
    }
}

// ── Tests ────────────────────────────────────────────────────────

it('executes tool calls successfully', function () {
    $toolkit = new MbcToolkit(Container::getInstance());
    $toolkit->register([AgentSuccessTool::class]);

    $agent = new MbcAgent($toolkit);

    $results = $agent->executeTools([
        new ToolCall(id: 'toolu_1', name: 'agent_success_tool', input: ['value' => 'hello']),
    ]);

    expect($results)->toHaveCount(1);
    expect($results[0]->isError)->toBeFalse();
    expect($results[0]->toolName)->toBe('agent_success_tool');
    expect($results[0]->toolUseId)->toBe('toolu_1');
    expect($results[0]->content)->toBe(['received' => 'hello']);
});

it('catches tool exceptions and returns error result', function () {
    $toolkit = new MbcToolkit(Container::getInstance());
    $toolkit->register([AgentFailingTool::class]);

    $agent = new MbcAgent($toolkit);

    $results = $agent->executeTools([
        new ToolCall(id: 'toolu_2', name: 'agent_failing_tool', input: []),
    ]);

    expect($results)->toHaveCount(1);
    expect($results[0]->isError)->toBeTrue();
    expect($results[0]->content)->toContain('Tool exploded');
});

it('executes multiple tool calls', function () {
    $toolkit = new MbcToolkit(Container::getInstance());
    $toolkit->register([AgentSuccessTool::class, AgentFailingTool::class]);

    $agent = new MbcAgent($toolkit);

    $results = $agent->executeTools([
        new ToolCall(id: 'toolu_a', name: 'agent_success_tool', input: ['value' => 'first']),
        new ToolCall(id: 'toolu_b', name: 'agent_failing_tool', input: []),
    ]);

    expect($results)->toHaveCount(2);
    expect($results[0]->isError)->toBeFalse();
    expect($results[1]->isError)->toBeTrue();
});
