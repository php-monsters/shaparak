<?php

namespace Asanpay\Shaparak\Facades;

use Illuminate\Support\Facades\Facade;
use Asanpay\Shaparak\Contracts\Factory;

/**
 * @method static \Asanpay\Shaparak\Contracts\Provider driver(string $driver = null)
 * @see \Asanpay\Shaparak\ShaparakManager
 */
class Shaparak extends Facade
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return Factory::class;
    }
}
