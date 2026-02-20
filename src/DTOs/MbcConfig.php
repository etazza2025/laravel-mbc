<?php

declare(strict_types=1);

namespace Undergrace\Mbc\DTOs;

final readonly class MbcConfig
{
    public function __construct(
        public int $maxTurns = 30,
        public int $maxTokensPerTurn = 4096,
        public string $model = 'claude-sonnet-4-5-20250929',
        public float $temperature = 1.0,
        public int $timeoutSeconds = 120,
        public int $retryTimes = 3,
        public int $retrySleepMs = 1000,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            maxTurns: $data['max_turns'] ?? 30,
            maxTokensPerTurn: $data['max_tokens_per_turn'] ?? 4096,
            model: $data['model'] ?? 'claude-sonnet-4-5-20250929',
            temperature: (float) ($data['temperature'] ?? 1.0),
            timeoutSeconds: $data['timeout_seconds'] ?? 120,
            retryTimes: $data['retry_times'] ?? 3,
            retrySleepMs: $data['retry_sleep_ms'] ?? 1000,
        );
    }

    public function toArray(): array
    {
        return [
            'max_turns' => $this->maxTurns,
            'max_tokens_per_turn' => $this->maxTokensPerTurn,
            'model' => $this->model,
            'temperature' => $this->temperature,
            'timeout_seconds' => $this->timeoutSeconds,
            'retry_times' => $this->retryTimes,
            'retry_sleep_ms' => $this->retrySleepMs,
        ];
    }
}
