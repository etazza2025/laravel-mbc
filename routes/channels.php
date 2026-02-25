<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| MBC Broadcast Channels
|--------------------------------------------------------------------------
|
| These channels are private by default — only authenticated users can
| subscribe. Override the authorization callback in your application's
| channels.php or via config if you need custom access control.
|
*/

$prefix = config('mbc.broadcasting.channel_prefix', 'mbc');

Broadcast::channel("{$prefix}.sessions.{uuid}", function ($user) {
    return $user !== null;
});

Broadcast::channel("{$prefix}.monitor", function ($user) {
    return $user !== null;
});
