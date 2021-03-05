<?php

namespace Asanpay\Shaparak\Contracts;

interface Factory
{
    /**
     * Get an Shetab provider implementation.
     *
     * @param  string  $driver
     * @return Provider
     */
    public function driver($driver = null);
}
