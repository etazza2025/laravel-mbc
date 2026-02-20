<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Undergrace\Mbc\Core\MbcToolkit;
use Undergrace\Mbc\Tools\Attributes\Tool;
use Undergrace\Mbc\Tools\Attributes\ToolParam;
use Undergrace\Mbc\Tools\BaseTool;

// ── Test tool ────────────────────────────────────────────────────

#[Tool(name: 'toolkit_test', description: 'Test tool for toolkit')]
class ToolkitTestTool extends BaseTool
{
    #[ToolParam(name: 'input', type: 'string', description: 'Test input')]
    public function execute(array $input): mixed
    {
        return 'result: ' . ($input['input'] ?? '');
    }
}

#[Tool(name: 'toolkit_test_2', description: 'Another test tool')]
class ToolkitTestTool2 extends BaseTool
{
    public function execute(array $input): mixed
    {
        return 'second tool';
    }
}

// ── Tests ────────────────────────────────────────────────────────

it('registers and resolves tools', function () {
    $toolkit = new MbcToolkit(Container::getInstance());
    $toolkit->register([ToolkitTestTool::class]);

    expect($toolkit->has('toolkit_test'))->toBeTrue();
    expect($toolkit->has('nonexistent'))->toBeFalse();
    expect($toolkit->count())->toBe(1);

    $tool = $toolkit->resolve('toolkit_test');
    expect($tool)->toBeInstanceOf(ToolkitTestTool::class);
});

it('returns tool definitions', function () {
    $toolkit = new MbcToolkit(Container::getInstance());
    $toolkit->register([ToolkitTestTool::class, ToolkitTestTool2::class]);

    $definitions = $toolkit->definitions();
    expect($definitions)->toHaveCount(2);

    $names = array_map(fn ($d) => $d->name, $definitions);
    expect($names)->toContain('toolkit_test');
    expect($names)->toContain('toolkit_test_2');
});

it('returns API-formatted definitions', function () {
    $toolkit = new MbcToolkit(Container::getInstance());
    $toolkit->register([ToolkitTestTool::class]);

    $apiFormat = $toolkit->toApiFormat();
    expect($apiFormat)->toHaveCount(1);
    expect($apiFormat[0])->toHaveKeys(['name', 'description', 'input_schema']);
});

it('throws when resolving unregistered tool', function () {
    $toolkit = new MbcToolkit(Container::getInstance());
    $toolkit->resolve('nonexistent');
})->throws(InvalidArgumentException::class, 'not registered');
