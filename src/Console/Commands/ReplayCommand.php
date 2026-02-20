<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Undergrace\Mbc\Core\MbcSession;
use Undergrace\Mbc\Models\MbcSession as MbcSessionModel;

#[AsCommand(name: 'mbc:replay')]
class ReplayCommand extends Command
{
    protected $signature = 'mbc:replay {uuid : The session UUID to replay}
                            {--message= : Override the initial message}';

    protected $description = 'Replay an MBC session with the same configuration';

    public function handle(): int
    {
        $uuid = $this->argument('uuid');
        $sessionModel = MbcSessionModel::where('uuid', $uuid)->first();

        if (! $sessionModel) {
            $this->error("Session not found: {$uuid}");

            return self::FAILURE;
        }

        $this->info("Replaying session: {$sessionModel->name}");
        $this->info("Original status: {$sessionModel->status->value}");
        $this->newLine();

        // Reconstruct the session from the stored configuration
        $session = new MbcSession($sessionModel->name . ' (replay)');
        $session->systemPrompt($sessionModel->system_prompt);

        if ($sessionModel->config) {
            $config = $sessionModel->config;
            $session->config(
                maxTurns: $config['max_turns'] ?? null,
                maxTokensPerTurn: $config['max_tokens_per_turn'] ?? null,
                model: $config['model'] ?? null,
                temperature: isset($config['temperature']) ? (float) $config['temperature'] : null,
            );
        }

        // Get the initial message from the first turn or use the override
        $initialMessage = $this->option('message');

        if (! $initialMessage) {
            $firstTurn = $sessionModel->turns()->orderBy('turn_number')->first();

            if ($firstTurn && is_array($firstTurn->content)) {
                // Try to extract text from content blocks
                $initialMessage = collect($firstTurn->content)
                    ->filter(fn ($block) => is_array($block) && ($block['type'] ?? '') === 'text')
                    ->pluck('text')
                    ->implode("\n");
            }

            if (! $initialMessage) {
                $initialMessage = is_string($firstTurn?->content)
                    ? $firstTurn->content
                    : 'Replay session';
            }
        }

        $this->info("Starting replay...");

        try {
            $session->start($initialMessage);
            $result = $session->result();

            $this->newLine();
            $this->info("Replay completed!");
            $this->table(
                ['Property', 'Value'],
                [
                    ['New UUID', $result->uuid],
                    ['Status', $result->status->value],
                    ['Turns', $result->totalTurns],
                    ['Cost', '$' . number_format($result->estimatedCostUsd, 6)],
                ],
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Replay failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
