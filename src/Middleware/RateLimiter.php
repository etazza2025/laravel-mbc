<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Middleware;

use Closure;
use RuntimeException;
use Undergrace\Mbc\Contracts\MbcMiddlewareInterface;
use Undergrace\Mbc\DTOs\ProviderResponse;

class RateLimiter implements MbcMiddlewareInterface
{
    private int $turnsProcessed = 0;

    public function __construct(
        private readonly int $maxTurns = 50,
    ) {}

    /**
     * Static factory for fluent configuration.
     *
     * Usage: RateLimiter::max(50)
     */
    public static function max(int $turns): self
    {
        return new self(maxTurns: $turns);
    }

    public function afterResponse(ProviderResponse $response, Closure $next): ProviderResponse
    {
        $this->turnsProcessed++;

        if ($this->turnsProcessed > $this->maxTurns) {
            throw new RuntimeException(
                "MBC RateLimiter: exceeded maximum of {$this->maxTurns} turns."
            );
        }

        return $next($response);
    }

    public function afterToolExecution(array $toolResults, Closure $next): array
    {
        return $next($toolResults);
    }
}
