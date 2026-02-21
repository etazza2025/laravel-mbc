<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Undergrace\Mbc\Http\Controllers\SessionController;
use Undergrace\Mbc\Http\Controllers\StatsController;
use Undergrace\Mbc\Http\Controllers\TurnController;

/*
|--------------------------------------------------------------------------
| MBC API Routes (read-only)
|--------------------------------------------------------------------------
|
| These routes provide monitoring and observability for MBC sessions.
| Enable them by setting MBC_API_ENABLED=true in your .env file.
| Configure middleware in config/mbc.php under 'api.middleware'.
|
*/

Route::get('/sessions', [SessionController::class, 'index']);
Route::get('/sessions/{uuid}', [SessionController::class, 'show']);
Route::get('/sessions/{uuid}/turns', [TurnController::class, 'index']);

Route::get('/stats', [StatsController::class, 'index']);
Route::get('/agents/active', [StatsController::class, 'active']);
