<?php

declare(strict_types=1);

namespace Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Undergrace\Mbc\MbcServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            MbcServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Mbc' => \Undergrace\Mbc\Facades\Mbc::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('mbc.providers.anthropic.api_key', 'test-api-key');
        $app['config']->set('mbc.storage.persist_sessions', false);
        $app['config']->set('mbc.storage.persist_turns', false);
        $app['config']->set('mbc.middleware', []);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
