<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Core;

use Undergrace\Mbc\DTOs\SessionResult;
use Undergrace\Mbc\Enums\SessionStatus;

final class OrchestratorResult
{
    public function __construct(
        public readonly string $name,
        /** @var SessionResult[] */
        private readonly array $results,
        /** @var string[] */
        private readonly array $uuids,
    ) {}

    /**
     * Get all agent results.
     *
     * @return SessionResult[]
     */
    public function all(): array
    {
        return $this->results;
    }

    /**
     * Get a specific agent's result by UUID.
     */
    public function get(string $uuid): ?SessionResult
    {
        foreach ($this->results as $result) {
            if ($result->uuid === $uuid) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Check if all agents completed successfully.
     */
    public function successful(): bool
    {
        foreach ($this->results as $result) {
            if ($result->status !== SessionStatus::COMPLETED) {
                return false;
            }
        }

        return ! empty($this->results);
    }

    /**
     * Get all failed agent results.
     *
     * @return SessionResult[]
     */
    public function failures(): array
    {
        return array_filter(
            $this->results,
            fn (SessionResult $r) => $r->status === SessionStatus::FAILED,
        );
    }

    /**
     * Merge all agents' final messages into a combined context.
     * Useful for feeding into a "summarizer" agent.
     */
    public function mergedOutput(): array
    {
        $merged = [];

        foreach ($this->results as $result) {
            $merged[] = [
                'agent_uuid' => $result->uuid,
                'status' => $result->status->value,
                'output' => $result->finalMessage,
                'cost_usd' => $result->estimatedCostUsd,
            ];
        }

        return $merged;
    }

    /**
     * Total cost across all agents.
     */
    public function totalCost(): float
    {
        return array_sum(array_map(
            fn (SessionResult $r) => $r->estimatedCostUsd,
            $this->results,
        ));
    }

    /**
     * Total tokens across all agents.
     */
    public function totalTokens(): int
    {
        return array_sum(array_map(
            fn (SessionResult $r) => $r->totalInputTokens + $r->totalOutputTokens,
            $this->results,
        ));
    }

    public function agentCount(): int
    {
        return count($this->results);
    }
}
