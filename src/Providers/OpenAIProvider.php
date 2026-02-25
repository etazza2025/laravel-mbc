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
 * OpenAI Provider — GPT-4o, o1, o3 and other OpenAI models.
 *
 * @see https://platform.openai.com/docs/api-reference/chat
 */
class OpenAIProvider implements MbcProviderInterface
{
    private readonly string $apiKey;
    private readonly string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('mbc.providers.openai.api_key', '');
        $this->baseUrl = config('mbc.providers.openai.base_url', 'https://api.openai.com/v1');

        if (empty($this->apiKey)) {
            throw new \RuntimeException('MBC: OPENAI_API_KEY is not configured. Set it in your .env file.');
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
     * Convert MBC messages to OpenAI format.
     * Injects system prompt as first message and converts tool_result blocks.
     */
    private function buildMessages(string $system, array $messages): array
    {
        $openAiMessages = [];

        // System prompt as first message
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

                if (! empty($textParts)) {
                    $msg['content'] = implode("\n", $textParts);
                } else {
                    $msg['content'] = null;
                }

                if (! empty($toolCalls)) {
                    $msg['tool_calls'] = $toolCalls;
                }

                $openAiMessages[] = $msg;

                continue;
            }

            // Regular user/assistant text messages
            $openAiMessages[] = [
                'role' => $role,
                'content' => is_string($content) ? $content : json_encode($content),
            ];
        }

        return $openAiMessages;
    }

    /**
     * Build the HTTP client with OpenAI-specific headers and retry logic.
     */
    private function client(MbcConfig $config): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ])
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
     * Parse the raw OpenAI API response into a typed ProviderResponse.
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

        // Parse tool calls
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
