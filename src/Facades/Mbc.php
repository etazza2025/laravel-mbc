<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Facades;

use Illuminate\Support\Facades\Facade;
use Undergrace\Mbc\Core\MbcSession;

/**
 * @method static MbcSession session(string $name)
 *
 * @see \Undergrace\Mbc\Core\MbcSession
 */
class Mbc extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'mbc';
    }
}
