<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Core;

use Undergrace\Mbc\DTOs\ToolCall;
use Undergrace\Mbc\DTOs\ToolResult;
use Undergrace\Mbc\Events\MbcToolExecuted;

class MbcAgent
{
    public function __construct(
        private readonly MbcToolkit $toolkit,
    ) {}

    /**
     * Execute all tool calls from the AI response and return results.
     *
     * Each tool is resolved from the toolkit and executed. If a tool throws
     * an exception, the error is captured and returned as an error ToolResult
     * so the AI can handle it gracefully.
     *
     * @param ToolCall[] $toolCalls
     * @return ToolResult[]
     */
    public function executeTools(array $toolCalls): array
    {
        $results = [];

        foreach ($toolCalls as $toolCall) {
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
                $result = new ToolResult(
                    toolUseId: $toolCall->id,
                    toolName: $toolCall->name,
                    content: "Error executing tool '{$toolCall->name}': {$e->getMessage()}",
                    isError: true,
                );
            }

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            event(new MbcToolExecuted($toolCall, $result, $durationMs));

            $results[] = $result;
        }

        return $results;
    }
}
