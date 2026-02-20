<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Core;

use Undergrace\Mbc\DTOs\SessionResult;
use Undergrace\Mbc\Enums\SessionStatus;

/**
 * Wrapper around the final session result with convenience methods.
 */
final readonly class MbcResult
{
    public function __construct(
        private SessionResult $sessionResult,
    ) {}

    public function uuid(): string
    {
        return $this->sessionResult->uuid;
    }

    public function status(): SessionStatus
    {
        return $this->sessionResult->status;
    }

    public function isCompleted(): bool
    {
        return $this->sessionResult->status === SessionStatus::COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->sessionResult->status === SessionStatus::FAILED;
    }

    public function finalMessage(): ?string
    {
        return $this->sessionResult->finalMessage;
    }

    public function totalTurns(): int
    {
        return $this->sessionResult->totalTurns;
    }

    public function totalInputTokens(): int
    {
        return $this->sessionResult->totalInputTokens;
    }

    public function totalOutputTokens(): int
    {
        return $this->sessionResult->totalOutputTokens;
    }

    public function estimatedCostUsd(): float
    {
        return $this->sessionResult->estimatedCostUsd;
    }

    public function metadata(): array
    {
        return $this->sessionResult->metadata;
    }

    public function toSessionResult(): SessionResult
    {
        return $this->sessionResult;
    }

    public function toArray(): array
    {
        return $this->sessionResult->toArray();
    }
}
