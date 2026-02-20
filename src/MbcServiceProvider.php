<?php

declare(strict_types=1);

namespace Undergrace\Mbc;

use Illuminate\Support\ServiceProvider;
use Undergrace\Mbc\Console\Commands\MakeMbcToolCommand;
use Undergrace\Mbc\Console\Commands\ReplayCommand;
use Undergrace\Mbc\Console\Commands\SessionStatusCommand;
use Undergrace\Mbc\Contracts\MbcProviderInterface;
use Undergrace\Mbc\Core\MbcSession;
use Undergrace\Mbc\Providers\AnthropicProvider;
use Undergrace\Mbc\Providers\OpenAIProvider;
use Undergrace\Mbc\Providers\OpenRouterProvider;

class MbcServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/mbc.php', 'mbc');

        // Bind the AI provider based on configuration
        $this->app->bind(MbcProviderInterface::class, function () {
            $provider = config('mbc.default_provider', 'anthropic');

            return match ($provider) {
                'anthropic' => new AnthropicProvider(),
                'openai' => new OpenAIProvider(),
                'openrouter' => new OpenRouterProvider(),
                default => throw new \InvalidArgumentException("Unknown MBC provider: [{$provider}]."),
            };
        });

        // Session factory — the facade accessor
        $this->app->bind('mbc', function () {
            return new class
            {
                /**
                 * Create a new MBC session with the given name.
                 */
                public function session(string $name): MbcSession
                {
                    return new MbcSession($name);
                }
            };
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->registerPublishing();
            $this->registerCommands();
        }

        $this->registerMigrations();
    }

    private function registerPublishing(): void
    {
        $this->publishes([
            __DIR__ . '/../config/mbc.php' => config_path('mbc.php'),
        ], 'mbc-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'mbc-migrations');
    }

    private function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    private function registerCommands(): void
    {
        $this->commands([
            MakeMbcToolCommand::class,
            SessionStatusCommand::class,
            ReplayCommand::class,
        ]);
    }
}
