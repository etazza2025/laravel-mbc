<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| MBC Broadcast Channels
|--------------------------------------------------------------------------
|
| These channels are used for real-time broadcasting of MBC events.
| By default, channels are public. Override authorization in your
| application's channels.php if you need to restrict access.
|
*/

$prefix = config('mbc.broadcasting.channel_prefix', 'mbc');

Broadcast::channel("{$prefix}.sessions.{uuid}", fn () => true);
Broadcast::channel("{$prefix}.monitor", fn () => true);
