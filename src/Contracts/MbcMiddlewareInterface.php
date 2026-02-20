<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Contracts;

use Closure;
use Undergrace\Mbc\DTOs\ProviderResponse;

interface MbcMiddlewareInterface
{
    /**
     * Called after receiving a response from the AI provider.
     */
    public function afterResponse(ProviderResponse $response, Closure $next): ProviderResponse;

    /**
     * Called after executing all tool calls for a turn.
     *
     * @param array<\Undergrace\Mbc\DTOs\ToolResult> $toolResults
     * @return array<\Undergrace\Mbc\DTOs\ToolResult>
     */
    public function afterToolExecution(array $toolResults, Closure $next): array;
}
