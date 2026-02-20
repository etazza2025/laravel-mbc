<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Core;

use Undergrace\Mbc\DTOs\SessionResult;
use Undergrace\Mbc\Enums\SessionStatus;

final class PipelineResult
{
    /** @var SessionResult[] */
    private array $results;

    public function __construct(array $results)
    {
        $this->results = $results;
    }

    /**
     * Get all stage results.
     *
     * @return SessionResult[]
     */
    public function all(): array
    {
        return $this->results;
    }

    /**
     * Get the result of a specific stage (0-indexed).
     */
    public function stage(int $index): ?SessionResult
    {
        return $this->results[$index] ?? null;
    }

    /**
     * Get the final stage's result.
     */
    public function final(): SessionResult
    {
        return $this->results[array_key_last($this->results)];
    }

    /**
     * Check if all stages completed successfully.
     */
    public function successful(): bool
    {
        foreach ($this->results as $result) {
            if ($result->status !== SessionStatus::COMPLETED) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the first failed stage, if any.
     */
    public function firstFailure(): ?SessionResult
    {
        foreach ($this->results as $result) {
            if ($result->status === SessionStatus::FAILED) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Total cost across all stages.
     */
    public function totalCost(): float
    {
        return array_sum(array_map(
            fn (SessionResult $r) => $r->estimatedCostUsd,
            $this->results,
        ));
    }

    /**
     * Total tokens across all stages.
     */
    public function totalTokens(): int
    {
        return array_sum(array_map(
            fn (SessionResult $r) => $r->totalInputTokens + $r->totalOutputTokens,
            $this->results,
        ));
    }

    /**
     * Number of stages executed.
     */
    public function stageCount(): int
    {
        return count($this->results);
    }
}
