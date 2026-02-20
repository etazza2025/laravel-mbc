<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Core;

/**
 * Token pricing per model (USD per 1M tokens).
 *
 * Prices are approximate and may change. Override via config if needed.
 */
final class ModelPricing
{
    /**
     * Known model pricing: [input_per_million, output_per_million].
     */
    private const PRICING = [
        // Anthropic
        'claude-sonnet-4-5-20250929' => [3.0, 15.0],
        'claude-sonnet-4'            => [3.0, 15.0],
        'claude-opus-4'              => [15.0, 75.0],
        'claude-haiku-3-5'           => [0.25, 1.25],

        // OpenAI
        'gpt-4o'                     => [2.50, 10.0],
        'gpt-4o-mini'                => [0.15, 0.60],
        'o1'                         => [15.0, 60.0],
        'o3'                         => [10.0, 40.0],
        'o3-mini'                    => [1.10, 4.40],

        // OpenRouter model IDs
        'anthropic/claude-sonnet-4'  => [3.0, 15.0],
        'anthropic/claude-opus-4'    => [15.0, 75.0],
        'anthropic/claude-haiku-3-5' => [0.25, 1.25],
        'openai/gpt-4o'              => [2.50, 10.0],
        'google/gemini-2.5-pro'      => [1.25, 10.0],
        'google/gemini-2.5-flash'    => [0.15, 0.60],
        'meta-llama/llama-4-scout'   => [0.15, 0.40],
        'meta-llama/llama-4-maverick' => [0.30, 0.80],
        'deepseek/deepseek-r1'       => [0.55, 2.19],
        'mistralai/mistral-large'    => [2.0, 6.0],
    ];

    /** Default fallback pricing if model is unknown. */
    private const DEFAULT_PRICING = [3.0, 15.0];

    public static function estimate(string $model, int $inputTokens, int $outputTokens): float
    {
        $pricing = self::PRICING[$model] ?? self::matchPartial($model) ?? self::DEFAULT_PRICING;

        return ($inputTokens * $pricing[0] / 1_000_000)
             + ($outputTokens * $pricing[1] / 1_000_000);
    }

    /**
     * Try to match by partial model name (handles versioned model IDs).
     */
    private static function matchPartial(string $model): ?array
    {
        foreach (self::PRICING as $key => $pricing) {
            if (str_contains($model, $key)) {
                return $pricing;
            }
        }

        return null;
    }

    /**
     * @return array{input: float, output: float}
     */
    public static function getPricing(string $model): array
    {
        $pricing = self::PRICING[$model] ?? self::matchPartial($model) ?? self::DEFAULT_PRICING;

        return ['input' => $pricing[0], 'output' => $pricing[1]];
    }
}
