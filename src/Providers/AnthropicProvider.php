<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Providers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Undergrace\Mbc\Contracts\MbcProviderInterface;
use Undergrace\Mbc\DTOs\MbcConfig;
use Undergrace\Mbc\DTOs\ProviderResponse;
use Undergrace\Mbc\DTOs\ToolCall;
use Undergrace\Mbc\DTOs\ToolDefinition;
use Undergrace\Mbc\Enums\StopReason;

class AnthropicProvider implements MbcProviderInterface
{
    private readonly string $apiKey;
    private readonly string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('mbc.providers.anthropic.api_key', '');
        $this->baseUrl = config('mbc.providers.anthropic.base_url', 'https://api.anthropic.com/v1');

        if (empty($this->apiKey)) {
            throw new \RuntimeException('MBC: ANTHROPIC_API_KEY is not configured. Set it in your .env file.');
        }
    }

    public function complete(
        string $system,
        array $messages,
        array $tools,
        MbcConfig $config,
    ): ProviderResponse {
        $payload = [
            'model' => $config->model,
            'max_tokens' => $config->maxTokensPerTurn,
            'system' => $system,
            'messages' => $messages,
            'temperature' => $config->temperature,
        ];

        if (! empty($tools)) {
            $payload['tools'] = array_map(
                fn (ToolDefinition $t) => $t->toApiFormat(),
                $tools,
            );
        }

        $response = $this->client($config)
            ->post("{$this->baseUrl}/messages", $payload)
            ->throw()
            ->json();

        return $this->parseResponse($response);
    }

    /**
     * Build the HTTP client with Anthropic-specific headers and retry logic.
     */
    private function client(MbcConfig $config): PendingRequest
    {
        return Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])
            ->timeout($config->timeoutSeconds)
            ->retry(
                $config->retryTimes,
                $config->retrySleepMs,
                fn (\Exception $exception, PendingRequest $request) => $this->shouldRetry($exception),
            );
    }

    /**
     * Determine if the request should be retried based on the exception.
     */
    private function shouldRetry(\Exception $exception): bool
    {
        if (! method_exists($exception, 'getCode')) {
            return false;
        }

        $code = $exception->getCode();

        // Retry on server errors (5xx) and rate limits (429)
        return $code >= 500 || $code === 429;
    }

    /**
     * Parse the raw Anthropic API response into a typed ProviderResponse.
     */
    private function parseResponse(array $response): ProviderResponse
    {
        $content = $response['content'] ?? [];
        $stopReason = StopReason::from($response['stop_reason']);

        $textParts = [];
        $toolCalls = [];

        foreach ($content as $block) {
            match ($block['type'] ?? null) {
                'text' => $textParts[] = $block['text'],
                'tool_use' => $toolCalls[] = ToolCall::fromApiBlock($block),
                default => null,
            };
        }

        return new ProviderResponse(
            id: $response['id'],
            stopReason: $stopReason,
            content: $content,
            toolCalls: $toolCalls,
            inputTokens: $response['usage']['input_tokens'] ?? 0,
            outputTokens: $response['usage']['output_tokens'] ?? 0,
            textContent: empty($textParts) ? null : implode("\n", $textParts),
        );
    }
}
