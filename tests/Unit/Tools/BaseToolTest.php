<?php

declare(strict_types=1);

use Undergrace\Mbc\Tools\Attributes\Tool;
use Undergrace\Mbc\Tools\Attributes\ToolParam;
use Undergrace\Mbc\Tools\BaseTool;

// ── Test tool classes ────────────────────────────────────────────

#[Tool(name: 'simple_tool', description: 'A simple test tool')]
class SimpleTestTool extends BaseTool
{
    public function execute(array $input): mixed
    {
        return 'done';
    }
}

#[Tool(name: 'param_tool', description: 'A tool with parameters')]
class ParamTestTool extends BaseTool
{
    #[ToolParam(name: 'query', type: 'string', description: 'Search query', required: true)]
    #[ToolParam(name: 'limit', type: 'integer', description: 'Max results', required: false)]
    #[ToolParam(name: 'category', type: 'string', description: 'Filter category', enum: ['tech', 'science', 'art'])]
    public function execute(array $input): mixed
    {
        return ['results' => []];
    }
}

class NoAttributeTool extends BaseTool
{
    public function execute(array $input): mixed
    {
        return null;
    }
}

// ── Tests ────────────────────────────────────────────────────────

it('extracts tool definition from class attribute', function () {
    $tool = new SimpleTestTool();
    $definition = $tool->definition();

    expect($definition->name)->toBe('simple_tool');
    expect($definition->description)->toBe('A simple test tool');
    expect($definition->inputSchema['type'])->toBe('object');
    expect($definition->inputSchema['required'])->toBe([]);
});

it('extracts parameter definitions from method attributes', function () {
    $tool = new ParamTestTool();
    $definition = $tool->definition();

    expect($definition->name)->toBe('param_tool');

    $props = $definition->inputSchema['properties'];
    expect($props)->toHaveKey('query');
    expect($props)->toHaveKey('limit');
    expect($props)->toHaveKey('category');

    expect($props['query']['type'])->toBe('string');
    expect($props['query']['description'])->toBe('Search query');

    expect($props['limit']['type'])->toBe('integer');

    expect($props['category']['enum'])->toBe(['tech', 'science', 'art']);
});

it('marks required parameters correctly', function () {
    $tool = new ParamTestTool();
    $definition = $tool->definition();

    $required = $definition->inputSchema['required'];
    expect($required)->toContain('query');
    expect($required)->toContain('category'); // required: true by default
    expect($required)->not->toContain('limit'); // explicitly required: false
});

it('throws when Tool attribute is missing', function () {
    $tool = new NoAttributeTool();
    $tool->definition();
})->throws(RuntimeException::class, 'must have the #[Tool] attribute');

it('produces valid API format', function () {
    $tool = new ParamTestTool();
    $apiFormat = $tool->definition()->toApiFormat();

    expect($apiFormat)->toHaveKeys(['name', 'description', 'input_schema']);
    expect($apiFormat['name'])->toBe('param_tool');
    expect($apiFormat['input_schema']['type'])->toBe('object');
});
