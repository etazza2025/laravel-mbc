<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Contracts;

use Undergrace\Mbc\DTOs\MbcConfig;
use Undergrace\Mbc\DTOs\ProviderResponse;
use Undergrace\Mbc\DTOs\ToolDefinition;

interface MbcProviderInterface
{
    /**
     * Send a completion request to the AI provider.
     *
     * @param string $system System prompt
     * @param array $messages Conversation history in API format
     * @param ToolDefinition[] $tools Available tool definitions
     * @param MbcConfig $config Session configuration
     */
    public function complete(
        string $system,
        array $messages,
        array $tools,
        MbcConfig $config,
    ): ProviderResponse;
}
