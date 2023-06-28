<?php

namespace PhpMonsters\Shaparak\Contracts;

interface Factory
{
    /**
     * Get a Shetab provider instance.
     *
     * @param  string|null  $driver
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function driver($driver = null);
}
