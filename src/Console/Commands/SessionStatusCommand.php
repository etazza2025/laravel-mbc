<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Undergrace\Mbc\Models\MbcSession;

#[AsCommand(name: 'mbc:session-status')]
class SessionStatusCommand extends Command
{
    protected $signature = 'mbc:session-status {uuid : The session UUID}';

    protected $description = 'Display the status of an MBC session';

    public function handle(): int
    {
        $uuid = $this->argument('uuid');
        $session = MbcSession::where('uuid', $uuid)->first();

        if (! $session) {
            $this->error("Session not found: {$uuid}");

            return self::FAILURE;
        }

        $this->info("Session: {$session->name}");
        $this->newLine();

        $this->table(
            ['Property', 'Value'],
            [
                ['UUID', $session->uuid],
                ['Status', $session->status->value],
                ['Model', $session->model],
                ['Total Turns', $session->total_turns],
                ['Input Tokens', number_format($session->total_input_tokens)],
                ['Output Tokens', number_format($session->total_output_tokens)],
                ['Estimated Cost', '$' . number_format((float) $session->estimated_cost_usd, 6)],
                ['Started At', $session->started_at?->format('Y-m-d H:i:s') ?? '-'],
                ['Completed At', $session->completed_at?->format('Y-m-d H:i:s') ?? '-'],
                ['Duration', $session->durationInSeconds() ? round($session->durationInSeconds(), 1) . 's' : '-'],
                ['Error', $session->error ?? '-'],
            ],
        );

        if ($session->turns->isNotEmpty()) {
            $this->newLine();
            $this->info('Turns:');

            $turns = $session->turns->map(fn ($turn) => [
                $turn->turn_number,
                $turn->type->value,
                $turn->stop_reason ?? '-',
                number_format($turn->input_tokens ?? 0),
                number_format($turn->output_tokens ?? 0),
                ($turn->duration_ms ?? 0) . 'ms',
            ]);

            $this->table(
                ['#', 'Type', 'Stop Reason', 'Input Tokens', 'Output Tokens', 'Duration'],
                $turns,
            );
        }

        return self::SUCCESS;
    }
}
