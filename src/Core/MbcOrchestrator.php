<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Core;

use Undergrace\Mbc\DTOs\SessionResult;
use Undergrace\Mbc\Jobs\RunMbcSessionJob;

/**
 * Orchestrator: run multiple MBC sessions in parallel and collect results.
 *
 * Launches agents as background jobs via Laravel queue and provides
 * methods to check progress and collect results.
 *
 * Usage:
 *   $orchestrator = MbcOrchestrator::create('build-site')
 *       ->agent($designerSession, 'Design the layout')
 *       ->agent($copywriterSession, 'Write the content')
 *       ->agent($seoSession, 'Optimize for search engines')
 *       ->dispatch();
 *
 *   // Later...
 *   $results = $orchestrator->results();    // Collect all results
 *   $done = $orchestrator->isComplete();    // Check if all finished
 */
class MbcOrchestrator
{
    private string $name;

    /** @var array<array{session: MbcSession, message: string, uuid: string|null}> */
    private array $agents = [];

    /** @var string[] UUIDs of dispatched sessions */
    private array $dispatchedUuids = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function create(string $name): self
    {
        return new self($name);
    }

    /**
     * Add an agent to the orchestrator.
     */
    public function agent(MbcSession $session, string $message): self
    {
        $this->agents[] = [
            'session' => $session,
            'message' => $message,
            'uuid' => null,
        ];

        return $this;
    }

    /**
     * Dispatch all agents as background jobs in parallel.
     *
     * @param string|null $queue Optional queue name
     */
    public function dispatch(?string $queue = null): self
    {
        foreach ($this->agents as $index => &$agent) {
            /** @var MbcSession $session */
            $session = $agent['session'];
            $agent['uuid'] = $session->uuid();

            $this->dispatchedUuids[] = $session->uuid();

            $job = new RunMbcSessionJob(
                $session->toSerializable(),
                $agent['message'],
            );

            if ($queue) {
                $job->onQueue($queue);
            }

            dispatch($job);
        }

        unset($agent);

        return $this;
    }

    /**
     * Run all agents synchronously (blocking) and return results.
     * Useful for testing or when you need results immediately.
     *
     * @return OrchestratorResult
     */
    public function runSync(): OrchestratorResult
    {
        $results = [];

        foreach ($this->agents as &$agent) {
            /** @var MbcSession $session */
            $session = $agent['session'];
            $agent['uuid'] = $session->uuid();
            $this->dispatchedUuids[] = $session->uuid();

            try {
                $session->start($agent['message']);
                $results[] = $session->result();
            } catch (\Throwable $e) {
                $results[] = new SessionResult(
                    uuid: $session->uuid(),
                    status: \Undergrace\Mbc\Enums\SessionStatus::FAILED,
                    finalMessage: null,
                    totalTurns: 0,
                    totalInputTokens: 0,
                    totalOutputTokens: 0,
                    estimatedCostUsd: 0.0,
                    metadata: ['error' => $e->getMessage()],
                );
            }
        }

        unset($agent);

        return new OrchestratorResult($this->name, $results, $this->dispatchedUuids);
    }

    /**
     * Get the UUIDs of all dispatched sessions.
     *
     * @return string[]
     */
    public function uuids(): array
    {
        return $this->dispatchedUuids;
    }

    /**
     * Check progress of dispatched sessions by querying the database.
     *
     * @return array{total: int, completed: int, running: int, failed: int, pending: int}
     */
    public function progress(): array
    {
        if (empty($this->dispatchedUuids)) {
            return ['total' => 0, 'completed' => 0, 'running' => 0, 'failed' => 0, 'pending' => 0];
        }

        $sessions = \Undergrace\Mbc\Models\MbcSession::whereIn('uuid', $this->dispatchedUuids)->get();

        return [
            'total' => count($this->dispatchedUuids),
            'completed' => $sessions->where('status', \Undergrace\Mbc\Enums\SessionStatus::COMPLETED)->count(),
            'running' => $sessions->where('status', \Undergrace\Mbc\Enums\SessionStatus::RUNNING)->count(),
            'failed' => $sessions->where('status', \Undergrace\Mbc\Enums\SessionStatus::FAILED)->count(),
            'pending' => $sessions->where('status', \Undergrace\Mbc\Enums\SessionStatus::PENDING)->count(),
        ];
    }

    /**
     * Check if all dispatched sessions have finished (completed or failed).
     */
    public function isComplete(): bool
    {
        $progress = $this->progress();

        return ($progress['completed'] + $progress['failed']) === $progress['total'];
    }

    /**
     * Collect results from all dispatched sessions.
     * Only works after sessions have finished (check with isComplete()).
     */
    public function results(): OrchestratorResult
    {
        $sessions = \Undergrace\Mbc\Models\MbcSession::whereIn('uuid', $this->dispatchedUuids)->get();
        $results = [];

        foreach ($sessions as $session) {
            $results[] = new SessionResult(
                uuid: $session->uuid,
                status: $session->status,
                finalMessage: $session->result['message'] ?? null,
                totalTurns: $session->total_turns,
                totalInputTokens: $session->total_input_tokens,
                totalOutputTokens: $session->total_output_tokens,
                estimatedCostUsd: (float) $session->estimated_cost_usd,
                metadata: [
                    'name' => $session->name,
                    'model' => $session->model,
                    'error' => $session->error,
                ],
            );
        }

        return new OrchestratorResult($this->name, $results, $this->dispatchedUuids);
    }
}
