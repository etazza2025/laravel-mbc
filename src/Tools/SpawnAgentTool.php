<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Tools;

use Undergrace\Mbc\Contracts\MbcToolInterface;
use Undergrace\Mbc\Core\MbcSession;
use Undergrace\Mbc\Tools\Attributes\Tool;
use Undergrace\Mbc\Tools\Attributes\ToolParam;

/**
 * Built-in tool that allows an agent to spawn a sub-agent.
 *
 * The parent agent can delegate a subtask to a specialized sub-agent
 * and receive the result back in the same conversation.
 *
 * The sub-agent's tools must be pre-registered via the registry.
 */
#[Tool(
    name: 'spawn_agent',
    description: 'Spawn a sub-agent to handle a specialized subtask. The sub-agent runs synchronously and returns its result. Use this when a task requires a different specialization or toolset.'
)]
class SpawnAgentTool extends BaseTool
{
    /** @var array<string, array{system_prompt: string, tools: array, model?: string, max_turns?: int}> */
    private array $registry = [];

    /**
     * Register a sub-agent profile that can be spawned.
     *
     * @param string $name The agent name (used by the AI to select which sub-agent to spawn)
     * @param string $systemPrompt The sub-agent's system prompt
     * @param array $toolClasses The sub-agent's available tools
     * @param string|null $model Optional model override
     * @param int $maxTurns Max turns for the sub-agent
     */
    public function register(
        string $name,
        string $systemPrompt,
        array $toolClasses,
        ?string $model = null,
        int $maxTurns = 15,
    ): self {
        $this->registry[$name] = [
            'system_prompt' => $systemPrompt,
            'tools' => $toolClasses,
            'model' => $model,
            'max_turns' => $maxTurns,
        ];

        return $this;
    }

    /**
     * Get the list of available sub-agent profiles for the AI to choose from.
     */
    public function availableAgents(): array
    {
        return array_keys($this->registry);
    }

    #[ToolParam(name: 'agent_name', type: 'string', description: 'Name of the sub-agent to spawn (from available agents)', required: true)]
    #[ToolParam(name: 'task', type: 'string', description: 'The task/instruction for the sub-agent', required: true)]
    #[ToolParam(name: 'context', type: 'string', description: 'Additional context or data the sub-agent needs')]
    public function execute(array $input): mixed
    {
        $agentName = $input['agent_name'];
        $task = $input['task'];
        $context = $input['context'] ?? '';

        if (! isset($this->registry[$agentName])) {
            $available = implode(', ', array_keys($this->registry));

            return [
                'error' => "Unknown sub-agent '{$agentName}'. Available: {$available}",
            ];
        }

        $profile = $this->registry[$agentName];

        $session = new MbcSession("sub:{$agentName}");
        $session->systemPrompt($profile['system_prompt']);
        $session->tools($profile['tools']);
        $session->config(
            maxTurns: $profile['max_turns'],
            model: $profile['model'],
        );

        if ($context !== '') {
            $session->context(['parent_context' => $context]);
        }

        try {
            $session->start($task);
            $result = $session->result();

            return [
                'agent' => $agentName,
                'status' => $result->status->value,
                'output' => $result->finalMessage,
                'turns_used' => $result->totalTurns,
                'cost_usd' => round($result->estimatedCostUsd, 6),
            ];
        } catch (\Throwable $e) {
            return [
                'agent' => $agentName,
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }
}
