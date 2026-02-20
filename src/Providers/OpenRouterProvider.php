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

/**
 * OpenRouter Provider — Access 200+ models via a single API.
 *
 * Supports: Claude, GPT-4o, Gemini, Llama, Mistral, DeepSeek, and more.
 * Uses the OpenAI-compatible chat completions format.
 *
 * @see https://openrouter.ai/docs
 */
class OpenRouterProvider implements MbcProviderInterface
{
    private readonly string $apiKey;
    private readonly string $baseUrl;
    private readonly ?string $siteUrl;
    private readonly ?string $siteName;

    public function __construct()
    {
        $this->apiKey = config('mbc.providers.openrouter.api_key');
        $this->baseUrl = config('mbc.providers.openrouter.base_url', 'https://openrouter.ai/api/v1');
        $this->siteUrl = config('mbc.providers.openrouter.site_url');
        $this->siteName = config('mbc.providers.openrouter.site_name');
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
            'temperature' => $config->temperature,
            'messages' => $this->buildMessages($system, $messages),
        ];

        if (! empty($tools)) {
            $payload['tools'] = array_map(
                fn (ToolDefinition $t) => $t->toOpenAiFormat(),
                $tools,
            );
        }

        $response = $this->client($config)
            ->post("{$this->baseUrl}/chat/completions", $payload)
            ->throw()
            ->json();

        return $this->parseResponse($response);
    }

    /**
     * Convert MBC messages to OpenAI-compatible format.
     */
    private function buildMessages(string $system, array $messages): array
    {
        $openAiMessages = [];

        if ($system !== '') {
            $openAiMessages[] = [
                'role' => 'system',
                'content' => $system,
            ];
        }

        foreach ($messages as $message) {
            $role = $message['role'];
            $content = $message['content'];

            // Anthropic tool_result format → OpenAI tool messages
            if ($role === 'user' && is_array($content) && isset($content[0]['type']) && $content[0]['type'] === 'tool_result') {
                foreach ($content as $block) {
                    $openAiMessages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $block['tool_use_id'],
                        'content' => is_string($block['content']) ? $block['content'] : json_encode($block['content']),
                    ];
                }

                continue;
            }

            // Anthropic assistant content blocks → OpenAI format
            if ($role === 'assistant' && is_array($content)) {
                $textParts = [];
                $toolCalls = [];

                foreach ($content as $block) {
                    if (($block['type'] ?? null) === 'text') {
                        $textParts[] = $block['text'];
                    } elseif (($block['type'] ?? null) === 'tool_use') {
                        $toolCalls[] = [
                            'id' => $block['id'],
                            'type' => 'function',
                            'function' => [
                                'name' => $block['name'],
                                'arguments' => json_encode(
                                    ! empty($block['input']) ? $block['input'] : new \stdClass()
                                ),
                            ],
                        ];
                    }
                }

                $msg = ['role' => 'assistant'];
                $msg['content'] = ! empty($textParts) ? implode("\n", $textParts) : null;

                if (! empty($toolCalls)) {
                    $msg['tool_calls'] = $toolCalls;
                }

                $openAiMessages[] = $msg;

                continue;
            }

            $openAiMessages[] = [
                'role' => $role,
                'content' => is_string($content) ? $content : json_encode($content),
            ];
        }

        return $openAiMessages;
    }

    /**
     * Build the HTTP client with OpenRouter-specific headers.
     */
    private function client(MbcConfig $config): PendingRequest
    {
        $headers = [
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ];

        // OpenRouter ranking headers (optional, improves rate limits)
        if ($this->siteUrl) {
            $headers['HTTP-Referer'] = $this->siteUrl;
        }

        if ($this->siteName) {
            $headers['X-Title'] = $this->siteName;
        }

        return Http::withHeaders($headers)
            ->timeout($config->timeoutSeconds)
            ->retry(
                $config->retryTimes,
                $config->retrySleepMs,
                fn (\Exception $exception) => $this->shouldRetry($exception),
            );
    }

    private function shouldRetry(\Exception $exception): bool
    {
        if (! method_exists($exception, 'getCode')) {
            return false;
        }

        $code = $exception->getCode();

        return $code >= 500 || $code === 429;
    }

    /**
     * Parse the OpenRouter response into a typed ProviderResponse.
     */
    private function parseResponse(array $response): ProviderResponse
    {
        $choice = $response['choices'][0] ?? [];
        $message = $choice['message'] ?? [];
        $finishReason = $choice['finish_reason'] ?? 'stop';

        $stopReason = match ($finishReason) {
            'stop' => StopReason::END_TURN,
            'tool_calls' => StopReason::TOOL_USE,
            'length' => StopReason::MAX_TOKENS,
            default => StopReason::END_TURN,
        };

        $textContent = $message['content'] ?? null;
        $toolCalls = [];

        foreach ($message['tool_calls'] ?? [] as $tc) {
            $toolCalls[] = ToolCall::fromOpenAiBlock($tc);
        }

        // Build Anthropic-compatible content blocks for MbcSession compatibility
        $content = [];

        if ($textContent !== null && $textContent !== '') {
            $content[] = ['type' => 'text', 'text' => $textContent];
        }

        foreach ($message['tool_calls'] ?? [] as $tc) {
            $content[] = [
                'type' => 'tool_use',
                'id' => $tc['id'],
                'name' => $tc['function']['name'],
                'input' => json_decode($tc['function']['arguments'] ?? '{}', true) ?: [],
            ];
        }

        return new ProviderResponse(
            id: $response['id'] ?? 'unknown',
            stopReason: $stopReason,
            content: $content,
            toolCalls: $toolCalls,
            inputTokens: $response['usage']['prompt_tokens'] ?? 0,
            outputTokens: $response['usage']['completion_tokens'] ?? 0,
            textContent: $textContent,
        );
    }
}
