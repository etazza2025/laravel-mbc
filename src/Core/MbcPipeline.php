<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Core;

use Undergrace\Mbc\DTOs\SessionResult;

/**
 * Pipeline: chain multiple MBC sessions in sequence.
 *
 * Each agent receives the previous agent's result as part of its context.
 * The output of Agent A becomes the input for Agent B, and so on.
 *
 * Usage:
 *   $result = MbcPipeline::create()
 *       ->pipe($architectSession, 'Design the database schema')
 *       ->pipe($backendSession, 'Implement the API based on the schema')
 *       ->pipe($frontendSession, 'Create the UI components for the API')
 *       ->run();
 */
class MbcPipeline
{
    /** @var array<array{session: MbcSession, message: string}> */
    private array $stages = [];

    private array $results = [];

    public static function create(): self
    {
        return new self();
    }

    /**
     * Add a stage to the pipeline.
     *
     * @param MbcSession $session A pre-configured session (with tools, systemPrompt, etc.)
     * @param string $message The initial message for this stage
     */
    public function pipe(MbcSession $session, string $message): self
    {
        $this->stages[] = [
            'session' => $session,
            'message' => $message,
        ];

        return $this;
    }

    /**
     * Execute the pipeline sequentially.
     *
     * Each stage receives:
     * - Its own configured context
     * - The accumulated results from all previous stages
     *
     * @return PipelineResult
     */
    public function run(): PipelineResult
    {
        $this->results = [];
        $accumulatedContext = [];

        foreach ($this->stages as $index => $stage) {
            /** @var MbcSession $session */
            $session = $stage['session'];
            $message = $stage['message'];

            // Inject previous agents' results into the message
            if (! empty($accumulatedContext)) {
                $previousResults = json_encode($accumulatedContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                $message .= "\n\n---\nResultados de agentes anteriores:\n```json\n{$previousResults}\n```";
            }

            $session->start($message);
            $result = $session->result();

            $this->results[] = $result;

            // Accumulate context for next stage
            $accumulatedContext[] = [
                'agent' => $result->uuid,
                'stage' => $index + 1,
                'status' => $result->status->value,
                'output' => $result->finalMessage,
                'tokens_used' => $result->totalInputTokens + $result->totalOutputTokens,
                'cost_usd' => $result->estimatedCostUsd,
            ];
        }

        return new PipelineResult($this->results);
    }
}
