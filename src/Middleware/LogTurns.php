<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Undergrace\Mbc\Contracts\MbcMiddlewareInterface;
use Undergrace\Mbc\DTOs\ProviderResponse;

class LogTurns implements MbcMiddlewareInterface
{
    public function afterResponse(ProviderResponse $response, Closure $next): ProviderResponse
    {
        $channel = config('mbc.logging.channel', 'mbc');

        Log::channel($channel)->info('MBC Turn Response', [
            'response_id' => $response->id,
            'stop_reason' => $response->stopReason->value,
            'tool_calls_count' => count($response->toolCalls),
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            'has_text' => $response->textContent !== null,
        ]);

        if (config('mbc.logging.log_responses', false) && $response->textContent) {
            Log::channel($channel)->debug('MBC Response Text', [
                'text' => $response->textContent,
            ]);
        }

        return $next($response);
    }

    public function afterToolExecution(array $toolResults, Closure $next): array
    {
        $channel = config('mbc.logging.channel', 'mbc');

        foreach ($toolResults as $result) {
            Log::channel($channel)->info('MBC Tool Executed', [
                'tool_name' => $result->toolName,
                'tool_use_id' => $result->toolUseId,
                'is_error' => $result->isError,
            ]);
        }

        return $next($toolResults);
    }
}
