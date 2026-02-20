<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Providers;

use RuntimeException;
use Undergrace\Mbc\Contracts\MbcProviderInterface;
use Undergrace\Mbc\DTOs\MbcConfig;
use Undergrace\Mbc\DTOs\ProviderResponse;

/**
 * OpenAI Provider — placeholder for future implementation.
 *
 * @see https://platform.openai.com/docs/api-reference
 */
class OpenAIProvider implements MbcProviderInterface
{
    public function complete(
        string $system,
        array $messages,
        array $tools,
        MbcConfig $config,
    ): ProviderResponse {
        throw new RuntimeException(
            'OpenAI provider is not yet implemented. Coming in MBC v0.2.'
        );
    }
}
