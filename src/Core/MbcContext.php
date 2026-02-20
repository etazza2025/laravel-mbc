<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Core;

use Illuminate\Support\Facades\Cache;

/**
 * Shared context between MBC agents.
 *
 * Acts as a key-value store backed by Laravel's cache system.
 * Multiple agents can read/write to the same context using a shared namespace.
 *
 * Usage:
 *   // In a tool or service
 *   $ctx = new MbcContext('project-123');
 *   $ctx->set('schema', $databaseSchema);
 *
 *   // In another agent's tool
 *   $ctx = new MbcContext('project-123');
 *   $schema = $ctx->get('schema');
 */
class MbcContext
{
    private string $namespace;
    private int $ttlMinutes;

    public function __construct(string $namespace, int $ttlMinutes = 120)
    {
        $this->namespace = $namespace;
        $this->ttlMinutes = $ttlMinutes;
    }

    /**
     * Store a value in the shared context.
     */
    public function set(string $key, mixed $value): void
    {
        Cache::put($this->prefixedKey($key), $value, now()->addMinutes($this->ttlMinutes));

        // Track all keys in this namespace
        $keys = $this->allKeys();
        if (! in_array($key, $keys, true)) {
            $keys[] = $key;
            Cache::put($this->prefixedKey('__keys__'), $keys, now()->addMinutes($this->ttlMinutes));
        }
    }

    /**
     * Retrieve a value from the shared context.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::get($this->prefixedKey($key), $default);
    }

    /**
     * Check if a key exists in the shared context.
     */
    public function has(string $key): bool
    {
        return Cache::has($this->prefixedKey($key));
    }

    /**
     * Append a value to an array stored in the context.
     */
    public function push(string $key, mixed $value): void
    {
        $current = $this->get($key, []);

        if (! is_array($current)) {
            $current = [$current];
        }

        $current[] = $value;
        $this->set($key, $current);
    }

    /**
     * Get all data in this context as an associative array.
     */
    public function all(): array
    {
        $data = [];

        foreach ($this->allKeys() as $key) {
            $data[$key] = $this->get($key);
        }

        return $data;
    }

    /**
     * Clear all data in this context.
     */
    public function flush(): void
    {
        foreach ($this->allKeys() as $key) {
            Cache::forget($this->prefixedKey($key));
        }

        Cache::forget($this->prefixedKey('__keys__'));
    }

    /**
     * Get all registered keys in this namespace.
     *
     * @return string[]
     */
    private function allKeys(): array
    {
        return Cache::get($this->prefixedKey('__keys__'), []);
    }

    private function prefixedKey(string $key): string
    {
        return "mbc_context:{$this->namespace}:{$key}";
    }
}
