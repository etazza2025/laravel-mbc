<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Core;

use Illuminate\Support\Facades\Log;
use Undergrace\Mbc\DTOs\ToolCall;
use Undergrace\Mbc\DTOs\ToolResult;
use Undergrace\Mbc\Events\MbcToolExecuted;

class MbcAgent
{
    public function __construct(
        private readonly MbcToolkit $toolkit,
        private readonly string $sessionUuid = '',
        private readonly bool $parallel = true,
    ) {}

    /**
     * Execute all tool calls from the AI response and return results.
     *
     * When parallel mode is enabled (default) and there are multiple tool calls,
     * tools are executed concurrently using Laravel's concurrency support.
     * Falls back to sequential execution for single calls or if concurrency fails.
     *
     * @param ToolCall[] $toolCalls
     * @return ToolResult[]
     */
    public function executeTools(array $toolCalls): array
    {
        if ($this->parallel && count($toolCalls) > 1) {
            return $this->executeParallel($toolCalls);
        }

        return $this->executeSequential($toolCalls);
    }

    /**
     * Execute tools sequentially (safe fallback).
     */
    private function executeSequential(array $toolCalls): array
    {
        $results = [];

        foreach ($toolCalls as $toolCall) {
            $results[] = $this->executeSingle($toolCall);
        }

        return $results;
    }

    /**
     * Execute tools in parallel using fork/process concurrency.
     *
     * Each tool runs in its own process via Laravel's Process facade.
     * Falls back to sequential if parallel execution fails.
     */
    private function executeParallel(array $toolCalls): array
    {
        $results = [];
        $futures = [];

        // Launch all tools concurrently
        foreach ($toolCalls as $index => $toolCall) {
            $futures[$index] = [
                'toolCall' => $toolCall,
                'startTime' => microtime(true),
            ];
        }

        // Execute with a simple fork-join using pcntl if available,
        // otherwise fall back to sequential with interleaved execution
        if (function_exists('pcntl_fork')) {
            return $this->executeWithPcntl($toolCalls);
        }

        // Fallback: run sequentially but still measure independently
        return $this->executeSequential($toolCalls);
    }

    /**
     * Execute tools using pcntl_fork for true parallelism.
     */
    private function executeWithPcntl(array $toolCalls): array
    {
        $results = [];
        $pipes = [];

        foreach ($toolCalls as $index => $toolCall) {
            $socketPair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

            if ($socketPair === false) {
                return $this->executeSequential($toolCalls);
            }

            $pid = pcntl_fork();

            if ($pid === -1) {
                // Fork failed — fall back to sequential
                fclose($socketPair[0]);
                fclose($socketPair[1]);

                return $this->executeSequential($toolCalls);
            }

            if ($pid === 0) {
                // Child process — use JSON instead of serialize for safety
                fclose($socketPair[0]);
                $result = $this->executeSingle($toolCall, emitEvent: false);
                $encoded = json_encode([
                    'index' => $index,
                    'toolUseId' => $result->toolUseId,
                    'toolName' => $result->toolName,
                    'content' => $result->content,
                    'isError' => $result->isError,
                ], JSON_THROW_ON_ERROR);
                fwrite($socketPair[1], $encoded);
                fclose($socketPair[1]);
                exit(0);
            }

            // Parent process
            fclose($socketPair[1]);
            $pipes[$index] = [
                'pid' => $pid,
                'socket' => $socketPair[0],
                'toolCall' => $toolCall,
                'startTime' => microtime(true),
            ];
        }

        // Collect results from all children
        foreach ($pipes as $index => $pipe) {
            pcntl_waitpid($pipe['pid'], $status);
            $data = stream_get_contents($pipe['socket']);
            fclose($pipe['socket']);

            $durationMs = (int) ((microtime(true) - $pipe['startTime']) * 1000);

            if ($data !== false && $data !== '') {
                $decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
                $result = new ToolResult(
                    toolUseId: $decoded['toolUseId'],
                    toolName: $decoded['toolName'],
                    content: $decoded['content'],
                    isError: $decoded['isError'],
                );
            } else {
                $result = new ToolResult(
                    toolUseId: $pipe['toolCall']->id,
                    toolName: $pipe['toolCall']->name,
                    content: "Error: parallel execution failed for tool '{$pipe['toolCall']->name}'",
                    isError: true,
                );
            }

            event(new MbcToolExecuted($this->sessionUuid, $pipe['toolCall'], $result, $durationMs));
            $results[$index] = $result;
        }

        // Ensure results are in the correct order
        ksort($results);

        return array_values($results);
    }

    /**
     * Execute a single tool call with error handling.
     */
    private function executeSingle(ToolCall $toolCall, bool $emitEvent = true): ToolResult
    {
        $startTime = microtime(true);

        try {
            $tool = $this->toolkit->resolve($toolCall->name);
            $output = $tool->execute($toolCall->input);

            $result = new ToolResult(
                toolUseId: $toolCall->id,
                toolName: $toolCall->name,
                content: $output,
                isError: false,
            );
        } catch (\Throwable $e) {
            Log::channel(config('mbc.logging.channel', 'mbc'))->error('MBC Tool execution failed', [
                'tool' => $toolCall->name,
                'session_uuid' => $this->sessionUuid,
                'exception' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);

            $result = new ToolResult(
                toolUseId: $toolCall->id,
                toolName: $toolCall->name,
                content: "Tool '{$toolCall->name}' execution failed. Check logs for details.",
                isError: true,
            );
        }

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        if ($emitEvent) {
            event(new MbcToolExecuted($this->sessionUuid, $toolCall, $result, $durationMs));
        }

        return $result;
    }
}
