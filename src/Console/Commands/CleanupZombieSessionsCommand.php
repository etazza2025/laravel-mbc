<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Undergrace\Mbc\Enums\SessionStatus;
use Undergrace\Mbc\Models\MbcSession;

#[AsCommand(name: 'mbc:cleanup')]
class CleanupZombieSessionsCommand extends Command
{
    protected $signature = 'mbc:cleanup
        {--timeout=60 : Minutes after which a RUNNING session is considered zombie}
        {--prune : Also delete sessions older than prune_after_days config}';

    protected $description = 'Clean up zombie sessions stuck in RUNNING state and optionally prune old sessions';

    public function handle(): int
    {
        $timeoutMinutes = (int) $this->option('timeout');

        // Fix zombie sessions (stuck in RUNNING)
        $cutoff = now()->subMinutes($timeoutMinutes);
        $zombies = MbcSession::where('status', SessionStatus::RUNNING)
            ->where('started_at', '<', $cutoff)
            ->get();

        if ($zombies->isNotEmpty()) {
            foreach ($zombies as $zombie) {
                $zombie->update([
                    'status' => SessionStatus::FAILED,
                    'error' => "Session timed out: stuck in RUNNING for more than {$timeoutMinutes} minutes.",
                    'completed_at' => now(),
                ]);
            }

            $this->info("Marked {$zombies->count()} zombie session(s) as FAILED.");
        } else {
            $this->info('No zombie sessions found.');
        }

        // Prune old sessions
        if ($this->option('prune')) {
            $days = (int) config('mbc.storage.prune_after_days', 30);
            $cutoffDate = now()->subDays($days);

            $pruned = MbcSession::where('created_at', '<', $cutoffDate)->count();

            if ($pruned > 0) {
                MbcSession::where('created_at', '<', $cutoffDate)->delete();
                $this->info("Pruned {$pruned} session(s) older than {$days} days.");
            } else {
                $this->info('No sessions to prune.');
            }
        }

        return self::SUCCESS;
    }
}
