<?php

namespace PhpMonsters\Shaparak\Facades;

use Illuminate\Support\Facades\Facade;
use PhpMonsters\Shaparak\Contracts\Factory;

/**
 * @method static \PhpMonsters\Shaparak\Contracts\Provider driver(string $driver = null)
 *
 * @see \PhpMonsters\Shaparak\ShaparakManager
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
