<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Core;

use Closure;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Undergrace\Mbc\Contracts\MbcMiddlewareInterface;
use Undergrace\Mbc\Contracts\MbcProviderInterface;
use Undergrace\Mbc\DTOs\MbcConfig;
use Undergrace\Mbc\DTOs\ProviderResponse;
use Undergrace\Mbc\DTOs\SessionResult;
use Undergrace\Mbc\DTOs\ToolResult;
use Undergrace\Mbc\Enums\SessionStatus;
use Undergrace\Mbc\Enums\StopReason;
use Undergrace\Mbc\Enums\TurnType;
use Undergrace\Mbc\Events\MbcSessionCompleted;
use Undergrace\Mbc\Events\MbcSessionFailed;
use Undergrace\Mbc\Events\MbcSessionStarted;
use Undergrace\Mbc\Events\MbcTurnCompleted;
use Undergrace\Mbc\Models\MbcSession as MbcSessionModel;
use Undergrace\Mbc\Models\MbcTurn as MbcTurnModel;

class MbcSession
{
    private string $uuid;
    private string $name;
    private string $systemPrompt = '';
    private array $toolClasses = [];
    private array $context = [];
    private array $middlewareClasses = [];
    private MbcConfig $config;
    private array $messages = [];
    private SessionStatus $status = SessionStatus::PENDING;
    private int $turnCount = 0;
    private int $totalInputTokens = 0;
    private int $totalOutputTokens = 0;
    private ?string $finalMessage = null;
    private ?string $error = null;
    private ?DateTimeImmutable $startedAt = null;
    private ?DateTimeImmutable $completedAt = null;

    /** Resolved at boot time */
    private ?MbcProviderInterface $provider = null;
    private ?MbcToolkit $toolkit = null;
    private ?MbcAgent $agent = null;

    /** @var MbcMiddlewareInterface[] */
    private array $middleware = [];

    /** Persistence model */
    private ?MbcSessionModel $model = null;

    public function __construct(string $name)
    {
        $this->uuid = (string) Str::uuid();
        $this->name = $name;
        $this->config = new MbcConfig();
    }

    // ─── Fluent Builder API ─────────────────────────────────────────────

    public function systemPrompt(string $prompt): self
    {
        $this->systemPrompt = $prompt;

        return $this;
    }

    /**
     * @param array<class-string<\Undergrace\Mbc\Contracts\MbcToolInterface>> $toolClasses
     */
    public function tools(array $toolClasses): self
    {
        $this->toolClasses = $toolClasses;

        return $this;
    }

    public function context(array $context): self
    {
        $this->context = $context;

        return $this;
    }

    public function config(
        ?int $maxTurns = null,
        ?int $maxTokensPerTurn = null,
        ?string $model = null,
        ?float $temperature = null,
        ?int $timeoutSeconds = null,
    ): self {
        $this->config = new MbcConfig(
            maxTurns: $maxTurns ?? $this->config->maxTurns,
            maxTokensPerTurn: $maxTokensPerTurn ?? $this->config->maxTokensPerTurn,
            model: $model ?? $this->config->model,
            temperature: $temperature ?? $this->config->temperature,
            timeoutSeconds: $timeoutSeconds ?? $this->config->timeoutSeconds,
        );

        return $this;
    }

    /**
     * @param array<class-string<MbcMiddlewareInterface>> $middlewareClasses
     */
    public function middleware(array $middlewareClasses): self
    {
        $this->middlewareClasses = $middlewareClasses;

        return $this;
    }

    // ─── Execution ──────────────────────────────────────────────────────

    /**
     * Start the multi-turn agent loop with the given initial message.
     */
    public function start(string $initialMessage): self
    {
        $this->guardConcurrency();
        $this->startedAt = new DateTimeImmutable();
        $this->boot();
        $this->persist();

        event(new MbcSessionStarted($this->uuid, $this->name));

        $this->messages = [
            [
                'role' => 'user',
                'content' => $this->buildInitialMessage($initialMessage),
            ],
        ];

        $this->status = SessionStatus::RUNNING;
        $this->updateModel();

        try {
            $this->runLoop();
        } catch (\Throwable $e) {
            $this->status = SessionStatus::FAILED;
            $this->error = $e->getMessage();
            $this->completedAt = new DateTimeImmutable();
            $this->updateModel();

            event(new MbcSessionFailed($this->uuid, $e->getMessage()));

            throw $e;
        }

        $this->completedAt = new DateTimeImmutable();
        $this->updateModel();

        event(new MbcSessionCompleted($this->uuid, $this->result()));

        return $this;
    }

    /**
     * The core multi-turn loop — the heart of the MBC protocol.
     */
    private function runLoop(): void
    {
        while ($this->turnCount < $this->config->maxTurns) {
            $this->turnCount++;
            $turnStart = microtime(true);

            // 1. Trim messages if approaching context window limit
            $this->trimMessagesIfNeeded();

            // 2. Call the AI Provider
            $response = $this->provider->complete(
                system: $this->systemPrompt,
                messages: $this->messages,
                tools: $this->toolkit->definitions(),
                config: $this->config,
            );

            // 2. Run afterResponse middleware pipeline
            $response = $this->runAfterResponseMiddleware($response);

            // 3. Track tokens
            $this->totalInputTokens += $response->inputTokens;
            $this->totalOutputTokens += $response->outputTokens;

            // 4. Add assistant message to conversation history
            $this->messages[] = [
                'role' => 'assistant',
                'content' => $response->content,
            ];

            $durationMs = (int) ((microtime(true) - $turnStart) * 1000);

            // 5. Persist the assistant turn
            $this->persistTurn(
                type: TurnType::ASSISTANT,
                content: $response->content,
                toolCalls: $response->toolCalls,
                inputTokens: $response->inputTokens,
                outputTokens: $response->outputTokens,
                stopReason: $response->stopReason->value,
                durationMs: $durationMs,
            );

            event(new MbcTurnCompleted(
                sessionUuid: $this->uuid,
                turnNumber: $this->turnCount,
                type: TurnType::ASSISTANT,
                stopReason: $response->stopReason,
            ));

            // 6. Check stop reason — if the AI finished, exit the loop
            if ($response->stopReason === StopReason::END_TURN) {
                $this->status = SessionStatus::COMPLETED;
                $this->finalMessage = $response->textContent;

                return;
            }

            if ($response->stopReason === StopReason::MAX_TOKENS) {
                $this->status = SessionStatus::COMPLETED;
                $this->finalMessage = $response->textContent;

                return;
            }

            // 7. If the AI wants to use tools → execute them locally
            if ($response->stopReason === StopReason::TOOL_USE && ! empty($response->toolCalls)) {
                $toolResults = $this->agent->executeTools($response->toolCalls);

                // 8. Run afterToolExecution middleware pipeline
                $toolResults = $this->runAfterToolExecutionMiddleware($toolResults);

                // 9. Build tool_result message in Anthropic API format
                $toolResultContent = array_map(
                    fn (ToolResult $r) => $r->toApiFormat(),
                    $toolResults,
                );

                $this->messages[] = [
                    'role' => 'user',
                    'content' => $toolResultContent,
                ];

                // 10. Persist the tool_result turn
                $this->persistTurn(
                    type: TurnType::TOOL_RESULT,
                    content: $toolResultContent,
                    toolResults: $toolResults,
                );
            }
        }

        // Exceeded max turns without the AI finishing
        $this->status = SessionStatus::MAX_TURNS;
    }

    // ─── Context Window Management ────────────────────────────────────

    /**
     * Estimate token count for a message (rough: ~4 chars per token).
     */
    private function estimateTokenCount(mixed $content): int
    {
        $text = is_string($content) ? $content : json_encode($content);

        return (int) ceil(strlen($text) / 4);
    }

    /**
     * Trim old messages when approaching the context window limit.
     *
     * Strategy: keep the first message (initial context) and the most recent
     * messages, dropping middle turns to stay within budget. Tool result pairs
     * are kept together to avoid orphaned tool_use/tool_result blocks.
     */
    private function trimMessagesIfNeeded(): void
    {
        $limit = $this->config->contextWindowLimit - $this->config->contextReserveTokens;

        $totalTokens = $this->estimateTokenCount($this->systemPrompt);
        foreach ($this->messages as $msg) {
            $totalTokens += $this->estimateTokenCount($msg['content']);
        }

        if ($totalTokens <= $limit) {
            return;
        }

        // Always preserve the first message (user context) and last 6 messages
        $preserveStart = 1;
        $preserveEnd = min(6, count($this->messages));

        if (count($this->messages) <= $preserveStart + $preserveEnd) {
            return;
        }

        $head = array_slice($this->messages, 0, $preserveStart);
        $tail = array_slice($this->messages, -$preserveEnd);

        // Build a summary marker so the AI knows history was trimmed
        $droppedCount = count($this->messages) - $preserveStart - $preserveEnd;
        $summary = [
            'role' => 'user',
            'content' => "[System: {$droppedCount} previous turns were trimmed to fit context window. "
                       . "The conversation started with the context above and the most recent turns follow.]",
        ];

        $this->messages = array_values(array_merge($head, [$summary], $tail));
    }

    // ─── Middleware Pipeline ────────────────────────────────────────────

    private function runAfterResponseMiddleware(ProviderResponse $response): ProviderResponse
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            fn (Closure $next, MbcMiddlewareInterface $mw) => fn (ProviderResponse $r) => $mw->afterResponse($r, $next),
            fn (ProviderResponse $r) => $r,
        );

        return $pipeline($response);
    }

    private function runAfterToolExecutionMiddleware(array $toolResults): array
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            fn (Closure $next, MbcMiddlewareInterface $mw) => fn (array $results) => $mw->afterToolExecution($results, $next),
            fn (array $results) => $results,
        );

        return $pipeline($toolResults);
    }

    // ─── Concurrency Guard ────────────────────────────────────────────

    /**
     * Check that we haven't exceeded the max concurrent sessions limit.
     *
     * @throws \RuntimeException
     */
    private function guardConcurrency(): void
    {
        $maxConcurrent = (int) config('mbc.limits.max_concurrent_sessions', 10);

        if ($maxConcurrent <= 0) {
            return;
        }

        $running = MbcSessionModel::where('status', SessionStatus::RUNNING)->count();

        if ($running >= $maxConcurrent) {
            throw new \RuntimeException(
                "MBC concurrency limit reached: {$running}/{$maxConcurrent} sessions are currently running."
            );
        }
    }

    // ─── Bootstrap ──────────────────────────────────────────────────────

    private function boot(): void
    {
        $container = app();

        // Resolve the AI provider
        $this->provider = $container->make(MbcProviderInterface::class);

        // Build the toolkit and register tools
        $this->toolkit = new MbcToolkit($container);
        $this->toolkit->register($this->toolClasses);

        // Build the agent
        $this->agent = new MbcAgent($this->toolkit, sessionUuid: $this->uuid);

        // Resolve middleware instances
        $globalMiddleware = config('mbc.middleware', []);
        $allMiddleware = array_merge($globalMiddleware, $this->middlewareClasses);

        foreach ($allMiddleware as $mwClass) {
            if (is_string($mwClass)) {
                $this->middleware[] = $container->make($mwClass);
            } elseif ($mwClass instanceof MbcMiddlewareInterface) {
                $this->middleware[] = $mwClass;
            }
        }
    }

    private function buildInitialMessage(string $message): string
    {
        if (empty($this->context)) {
            return $message;
        }

        $contextJson = json_encode($this->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return "{$message}\n\n---\nContexto inicial:\n```json\n{$contextJson}\n```";
    }

    // ─── Persistence ────────────────────────────────────────────────────

    private function persist(): void
    {
        if (! config('mbc.storage.persist_sessions', true)) {
            return;
        }

        $this->model = MbcSessionModel::create([
            'uuid' => $this->uuid,
            'name' => $this->name,
            'status' => $this->status,
            'model' => $this->config->model,
            'system_prompt' => $this->systemPrompt,
            'context' => $this->context,
            'config' => $this->config->toArray(),
        ]);
    }

    private function updateModel(): void
    {
        $this->model?->update([
            'status' => $this->status,
            'total_turns' => $this->turnCount,
            'total_input_tokens' => $this->totalInputTokens,
            'total_output_tokens' => $this->totalOutputTokens,
            'estimated_cost_usd' => $this->estimateCost(),
            'result' => $this->finalMessage ? ['message' => $this->finalMessage] : null,
            'error' => $this->error,
            'started_at' => $this->startedAt?->format('Y-m-d H:i:s'),
            'completed_at' => $this->completedAt?->format('Y-m-d H:i:s'),
        ]);
    }

    private function persistTurn(
        TurnType $type,
        array $content,
        array $toolCalls = [],
        array $toolResults = [],
        int $inputTokens = 0,
        int $outputTokens = 0,
        ?string $stopReason = null,
        int $durationMs = 0,
    ): void {
        if (! config('mbc.storage.persist_turns', true) || ! $this->model) {
            return;
        }

        MbcTurnModel::create([
            'session_id' => $this->model->id,
            'turn_number' => $this->turnCount,
            'type' => $type,
            'content' => $content,
            'tool_calls' => empty($toolCalls) ? null
                : array_map(fn ($tc) => $tc->toArray(), $toolCalls),
            'tool_results' => empty($toolResults) ? null
                : array_map(fn ($tr) => $tr->toApiFormat(), $toolResults),
            'input_tokens' => $inputTokens ?: null,
            'output_tokens' => $outputTokens ?: null,
            'stop_reason' => $stopReason,
            'duration_ms' => $durationMs ?: null,
            'created_at' => now(),
        ]);
    }

    private function estimateCost(): float
    {
        return ModelPricing::estimate(
            $this->config->model,
            $this->totalInputTokens,
            $this->totalOutputTokens,
        );
    }

    // ─── Accessors ──────────────────────────────────────────────────────

    public function uuid(): string
    {
        return $this->uuid;
    }

    public function status(): SessionStatus
    {
        return $this->status;
    }

    public function result(): SessionResult
    {
        return new SessionResult(
            uuid: $this->uuid,
            status: $this->status,
            finalMessage: $this->finalMessage,
            totalTurns: $this->turnCount,
            totalInputTokens: $this->totalInputTokens,
            totalOutputTokens: $this->totalOutputTokens,
            estimatedCostUsd: $this->estimateCost(),
            metadata: [],
        );
    }

    // ─── Serialization (for Jobs) ───────────────────────────────────────

    public function toSerializable(): array
    {
        return [
            'name' => $this->name,
            'system_prompt' => $this->systemPrompt,
            'tool_classes' => $this->toolClasses,
            'context' => $this->context,
            'middleware_classes' => $this->middlewareClasses,
            'config' => $this->config->toArray(),
        ];
    }

    public static function fromSerializable(array $data): self
    {
        $session = new self($data['name']);
        $session->systemPrompt = $data['system_prompt'];
        $session->toolClasses = $data['tool_classes'];
        $session->context = $data['context'];
        $session->middlewareClasses = $data['middleware_classes'];
        $session->config = MbcConfig::fromArray($data['config']);

        return $session;
    }
}
