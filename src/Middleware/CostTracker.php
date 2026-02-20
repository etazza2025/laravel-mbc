<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Undergrace\Mbc\Contracts\MbcMiddlewareInterface;
use Undergrace\Mbc\Core\ModelPricing;
use Undergrace\Mbc\DTOs\ProviderResponse;

class CostTracker implements MbcMiddlewareInterface
{
    private int $cumulativeInputTokens = 0;
    private int $cumulativeOutputTokens = 0;
    private string $model = 'claude-sonnet-4-5-20250929';

    public function afterResponse(ProviderResponse $response, Closure $next): ProviderResponse
    {
        $this->cumulativeInputTokens += $response->inputTokens;
        $this->cumulativeOutputTokens += $response->outputTokens;

        $estimatedCost = ModelPricing::estimate(
            $this->model,
            $this->cumulativeInputTokens,
            $this->cumulativeOutputTokens,
        );

        Log::channel(config('mbc.logging.channel', 'mbc'))->info('MBC Cost Tracker', [
            'model' => $this->model,
            'turn_input_tokens' => $response->inputTokens,
            'turn_output_tokens' => $response->outputTokens,
            'cumulative_input' => $this->cumulativeInputTokens,
            'cumulative_output' => $this->cumulativeOutputTokens,
            'estimated_cost_usd' => round($estimatedCost, 6),
        ]);

        return $next($response);
    }

    /**
     * Set the model for accurate cost calculation.
     */
    public function forModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function afterToolExecution(array $toolResults, Closure $next): array
    {
        return $next($toolResults);
    }
}
