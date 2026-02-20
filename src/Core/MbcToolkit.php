<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Core;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Undergrace\Mbc\Contracts\MbcToolInterface;
use Undergrace\Mbc\DTOs\ToolDefinition;

class MbcToolkit
{
    /** @var array<string, MbcToolInterface> Tool name => instance */
    private array $tools = [];

    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * Register tool classes. They are resolved via the Laravel container
     * to support constructor dependency injection.
     *
     * @param array<class-string<MbcToolInterface>> $toolClasses
     */
    public function register(array $toolClasses): void
    {
        foreach ($toolClasses as $class) {
            /** @var MbcToolInterface $tool */
            $tool = $this->container->make($class);
            $definition = $tool->definition();
            $this->tools[$definition->name] = $tool;
        }
    }

    /**
     * Get all tool definitions for the AI API.
     *
     * @return ToolDefinition[]
     */
    public function definitions(): array
    {
        return array_map(
            fn (MbcToolInterface $tool) => $tool->definition(),
            array_values($this->tools),
        );
    }

    /**
     * Get tool definitions in Anthropic API format.
     *
     * @return array[]
     */
    public function toApiFormat(): array
    {
        return array_map(
            fn (ToolDefinition $def) => $def->toApiFormat(),
            $this->definitions(),
        );
    }

    /**
     * Resolve a registered tool by its name.
     */
    public function resolve(string $name): MbcToolInterface
    {
        if (! isset($this->tools[$name])) {
            throw new InvalidArgumentException("Tool [{$name}] is not registered in the toolkit.");
        }

        return $this->tools[$name];
    }

    /**
     * Check if a tool is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * Get the count of registered tools.
     */
    public function count(): int
    {
        return count($this->tools);
    }
}
