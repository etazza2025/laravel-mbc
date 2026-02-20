<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Undergrace\Mbc\Contracts\MbcMiddlewareInterface;
use Undergrace\Mbc\DTOs\ProviderResponse;

class CostTracker implements MbcMiddlewareInterface
{
    private int $cumulativeInputTokens = 0;
    private int $cumulativeOutputTokens = 0;

    public function afterResponse(ProviderResponse $response, Closure $next): ProviderResponse
    {
        $this->cumulativeInputTokens += $response->inputTokens;
        $this->cumulativeOutputTokens += $response->outputTokens;

        // Claude Sonnet 4 pricing: ~$3/M input, ~$15/M output
        $estimatedCost = ($this->cumulativeInputTokens * 3 / 1_000_000)
                       + ($this->cumulativeOutputTokens * 15 / 1_000_000);

        Log::channel(config('mbc.logging.channel', 'mbc'))->info('MBC Cost Tracker', [
            'turn_input_tokens' => $response->inputTokens,
            'turn_output_tokens' => $response->outputTokens,
            'cumulative_input' => $this->cumulativeInputTokens,
            'cumulative_output' => $this->cumulativeOutputTokens,
            'estimated_cost_usd' => round($estimatedCost, 6),
        ]);

        return $next($response);
    }

    public function afterToolExecution(array $toolResults, Closure $next): array
    {
        return $next($toolResults);
    }
}
