<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Undergrace\Mbc\Enums\SessionStatus;
use Undergrace\Mbc\Events\MbcSessionCompleted;
use Undergrace\Mbc\Events\MbcSessionStarted;
use Undergrace\Mbc\Events\MbcToolExecuted;
use Undergrace\Mbc\Events\MbcTurnCompleted;
use Undergrace\Mbc\Facades\Mbc;
use Undergrace\Mbc\Models\MbcSession as MbcSessionModel;
use Undergrace\Mbc\Tools\Attributes\Tool;
use Undergrace\Mbc\Tools\Attributes\ToolParam;
use Undergrace\Mbc\Tools\BaseTool;

// ── Test tool ────────────────────────────────────────────────────

#[Tool(name: 'list_items', description: 'Lists available items')]
class FullFlowListTool extends BaseTool
{
    public function execute(array $input): mixed
    {
        return [
            'items' => [
                ['id' => 1, 'name' => 'Item A'],
                ['id' => 2, 'name' => 'Item B'],
            ],
        ];
    }
}

#[Tool(name: 'create_result', description: 'Creates the final result')]
class FullFlowCreateTool extends BaseTool
{
    #[ToolParam(name: 'items', type: 'array', description: 'Items to include')]
    public function execute(array $input): mixed
    {
        return [
            'result_id' => 'res_001',
            'items_count' => count($input['items'] ?? []),
        ];
    }
}

// ── Tests ────────────────────────────────────────────────────────

it('runs a complete multi-turn flow via the Facade', function () {
    Event::fake();

    // Enable persistence for this test
    config()->set('mbc.storage.persist_sessions', true);
    config()->set('mbc.storage.persist_turns', true);

    // Mock Anthropic API responses
    Http::fake([
        'api.anthropic.com/v1/messages' => Http::sequence()
            // Turn 1: AI calls list_items
            ->push([
                'id' => 'msg_flow_1',
                'stop_reason' => 'tool_use',
                'content' => [
                    ['type' => 'text', 'text' => 'Let me explore available items...'],
                    [
                        'type' => 'tool_use',
                        'id' => 'toolu_list',
                        'name' => 'list_items',
                        'input' => [],
                    ],
                ],
                'usage' => ['input_tokens' => 200, 'output_tokens' => 80],
            ])
            // Turn 2: AI calls create_result
            ->push([
                'id' => 'msg_flow_2',
                'stop_reason' => 'tool_use',
                'content' => [
                    ['type' => 'text', 'text' => 'I found items, let me create the result...'],
                    [
                        'type' => 'tool_use',
                        'id' => 'toolu_create',
                        'name' => 'create_result',
                        'input' => ['items' => [1, 2]],
                    ],
                ],
                'usage' => ['input_tokens' => 400, 'output_tokens' => 100],
            ])
            // Turn 3: AI finishes
            ->push([
                'id' => 'msg_flow_3',
                'stop_reason' => 'end_turn',
                'content' => [
                    ['type' => 'text', 'text' => 'I have created the result with 2 items. Everything looks great!'],
                ],
                'usage' => ['input_tokens' => 600, 'output_tokens' => 50],
            ]),
    ]);

    // Run the full flow
    $session = Mbc::session('full-flow-test')
        ->systemPrompt('You are a test agent. List items, then create a result.')
        ->tools([FullFlowListTool::class, FullFlowCreateTool::class])
        ->context(['test_mode' => true])
        ->config(maxTurns: 10, model: 'claude-sonnet-4-5-20250929')
        ->start('Create a result using available items.');

    $result = $session->result();

    // Verify the result
    expect($result->status)->toBe(SessionStatus::COMPLETED);
    expect($result->totalTurns)->toBe(3);
    expect($result->totalInputTokens)->toBe(1200);
    expect($result->totalOutputTokens)->toBe(230);
    expect($result->finalMessage)->toContain('2 items');

    // Verify events were dispatched
    Event::assertDispatched(MbcSessionStarted::class);
    Event::assertDispatched(MbcTurnCompleted::class, 3);
    Event::assertDispatched(MbcToolExecuted::class, 2);
    Event::assertDispatched(MbcSessionCompleted::class);

    // Verify persistence
    $savedSession = MbcSessionModel::where('uuid', $result->uuid)->first();
    expect($savedSession)->not->toBeNull();
    expect($savedSession->name)->toBe('full-flow-test');
    expect($savedSession->status)->toBe(SessionStatus::COMPLETED);
    expect($savedSession->total_turns)->toBe(3);
    expect($savedSession->turns)->toHaveCount(5); // 3 assistant + 2 tool_result

    // Verify Anthropic API was called 3 times
    Http::assertSentCount(3);
});

it('handles AI errors gracefully', function () {
    Http::fake([
        'api.anthropic.com/v1/messages' => Http::response([
            'type' => 'error',
            'error' => [
                'type' => 'invalid_request_error',
                'message' => 'Invalid API key',
            ],
        ], 401),
    ]);

    expect(function () {
        Mbc::session('error-test')
            ->systemPrompt('Test')
            ->config(maxTurns: 5)
            ->start('This should fail');
    })->toThrow(\Illuminate\Http\Client\RequestException::class);
});
