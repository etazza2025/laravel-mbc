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
use Undergrace\Mbc\Enums\SessionStatus;
use Undergrace\Mbc\Models\MbcSession as MbcSessionModel;

final class RunMbcSessionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Number of times the job may be attempted.
     * Retries on transient API errors (429, 5xx).
     */
    public int $tries = 3;

    /**
     * Seconds to wait before retrying.
     */
    public array $backoff = [10, 30, 60];

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

    /**
     * Handle a job failure.
     * Marks any orphaned session as FAILED so it doesn't stay as a zombie.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('MBC Session Job failed', [
            'error' => $exception->getMessage(),
            'session_name' => $this->sessionData['name'] ?? 'unknown',
            'attempt' => $this->attempts(),
        ]);

        // Clean up any zombie session left in RUNNING state by this job
        $sessionName = $this->sessionData['name'] ?? null;

        if ($sessionName) {
            MbcSessionModel::where('name', $sessionName)
                ->where('status', SessionStatus::RUNNING)
                ->orderByDesc('created_at')
                ->limit(1)
                ->update([
                    'status' => SessionStatus::FAILED,
                    'error' => "Job failed after {$this->attempts()} attempt(s): {$exception->getMessage()}",
                    'completed_at' => now(),
                ]);
        }
    }

    /**
     * Determine if the job should be retried based on the exception.
     */
    public function retryUntil(): \DateTime
    {
        // Give up after 45 minutes total (timeout + retry buffer)
        return now()->addMinutes(45);
    }
}
