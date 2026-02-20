<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Undergrace\Mbc\Core\MbcSession;

final class RunMbcSessionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * Timeout for the job in seconds (30 minutes for long AI sessions).
     */
    public int $timeout = 1800;

    public function __construct(
        private readonly array $sessionData,
        private readonly string $initialMessage,
    ) {}

    public function handle(): void
    {
        $session = MbcSession::fromSerializable($this->sessionData);
        $session->start($this->initialMessage);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('MBC Session Job failed', [
            'error' => $exception->getMessage(),
            'session_name' => $this->sessionData['name'] ?? 'unknown',
        ]);
    }
}
