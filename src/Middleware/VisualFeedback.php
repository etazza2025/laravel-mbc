<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Undergrace\Mbc\Contracts\MbcMiddlewareInterface;
use Undergrace\Mbc\Contracts\MbcRendererInterface;
use Undergrace\Mbc\DTOs\ProviderResponse;
use Undergrace\Mbc\DTOs\ToolResult;

class VisualFeedback implements MbcMiddlewareInterface
{
    /** Tool names that trigger visual feedback */
    private array $triggerTools;

    /** Key in the tool result content that contains the preview URL */
    private string $previewUrlKey;

    public function __construct(
        private readonly MbcRendererInterface $renderer,
        array $triggerTools = ['assemble_site'],
        string $previewUrlKey = 'preview_url',
    ) {
        $this->triggerTools = $triggerTools;
        $this->previewUrlKey = $previewUrlKey;
    }

    public function afterResponse(ProviderResponse $response, Closure $next): ProviderResponse
    {
        return $next($response);
    }

    public function afterToolExecution(array $toolResults, Closure $next): array
    {
        $toolResults = $next($toolResults);

        if (! config('mbc.visual_feedback.enabled', false)) {
            return $toolResults;
        }

        // Check if any trigger tool was executed
        $triggerResult = null;
        foreach ($toolResults as $result) {
            if (in_array($result->toolName, $this->triggerTools, true) && ! $result->isError) {
                $triggerResult = $result;
                break;
            }
        }

        if (! $triggerResult) {
            return $toolResults;
        }

        // Extract the preview URL from the tool result
        $previewUrl = $this->extractPreviewUrl($triggerResult);

        if (! $previewUrl) {
            return $toolResults;
        }

        try {
            // Capture screenshots at configured viewports
            $viewports = config('mbc.visual_feedback.viewports', [
                'desktop' => [1440, 900],
            ]);

            $screenshots = $this->renderer->capture($previewUrl, $viewports);

            // Build image content blocks for the AI
            $imageBlocks = [];
            foreach ($screenshots as $viewport => $base64) {
                $imageBlocks[] = [
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => 'image/png',
                        'data' => $base64,
                    ],
                ];
            }

            $imageBlocks[] = [
                'type' => 'text',
                'text' => 'Revisa el resultado visual. Si hay problemas de diseño '
                        . '(contraste, spacing, jerarquía, balance), usa las herramientas '
                        . 'disponibles para corregir. Si todo se ve profesional, termina.',
            ];

            // Inject as an additional tool result
            $toolResults[] = new ToolResult(
                toolUseId: $triggerResult->toolUseId . '_visual',
                toolName: '_visual_feedback',
                content: $imageBlocks,
                isError: false,
            );

            Log::channel(config('mbc.logging.channel', 'mbc'))->info('MBC Visual Feedback captured', [
                'preview_url' => $previewUrl,
                'viewports' => array_keys($screenshots),
            ]);
        } catch (\Throwable $e) {
            Log::channel(config('mbc.logging.channel', 'mbc'))->warning('MBC Visual Feedback failed', [
                'error' => $e->getMessage(),
                'preview_url' => $previewUrl,
            ]);
        }

        return $toolResults;
    }

    private function extractPreviewUrl(ToolResult $result): ?string
    {
        $content = $result->content;

        if (is_array($content) && isset($content[$this->previewUrlKey])) {
            return $content[$this->previewUrlKey];
        }

        if (is_string($content)) {
            $decoded = json_decode($content, true);
            if (is_array($decoded) && isset($decoded[$this->previewUrlKey])) {
                return $decoded[$this->previewUrlKey];
            }
        }

        return null;
    }
}
