<?php

declare(strict_types=1);

use Undergrace\Mbc\Middleware\CostTracker;
use Undergrace\Mbc\Middleware\LogTurns;

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    */

    'default_provider' => 'anthropic',

    /*
    |--------------------------------------------------------------------------
    | AI Providers Configuration
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'base_url' => 'https://api.anthropic.com/v1',
            'default_model' => 'claude-sonnet-4-5-20250929',
            'timeout' => 120,
            'retry' => [
                'times' => 3,
                'sleep' => 1000,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Limits
    |--------------------------------------------------------------------------
    */

    'limits' => [
        'max_turns_per_session' => 50,
        'max_tokens_per_turn' => 8192,
        'max_concurrent_sessions' => 10,
        'session_timeout_minutes' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Visual Feedback
    |--------------------------------------------------------------------------
    */

    'visual_feedback' => [
        'enabled' => false,
        'renderer' => 'browsershot',
        'viewports' => [
            'desktop' => ['width' => 1440, 'height' => 900],
            'mobile' => ['width' => 375, 'height' => 812],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Persistence
    |--------------------------------------------------------------------------
    */

    'storage' => [
        'persist_sessions' => true,
        'persist_turns' => true,
        'prune_after_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Middleware
    |--------------------------------------------------------------------------
    */

    'middleware' => [
        LogTurns::class,
        CostTracker::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */

    'logging' => [
        'channel' => 'mbc',
        'log_prompts' => env('MBC_LOG_PROMPTS', false),
        'log_responses' => env('MBC_LOG_RESPONSES', false),
    ],

];
