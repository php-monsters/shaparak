<?php

namespace Asanpay\Shaparak;

use Asanpay\Shaparak\Adapter\AbstractProvider;
use Asanpay\Shaparak\Contracts\Transaction;
use Asanpay\Shaparak\Contracts\Provider;
use Illuminate\Support\Arr;
use Illuminate\Support\Manager;
use InvalidArgumentException;
use Asanpay\Shaparak\Provider\SamanProvider;

class ShaparakManager extends Manager implements Contracts\Factory
{
    /**
     * runtime driver configuration
     *
     * @var array
     */
    protected $runtimeConfig;

    /**
     * transaction which should paid on the gateway
     *
     * @var Transaciton $transaction
     */
    protected $transaction;

    /**
     * Get a driver instance.
     *
     * @param string $driver driver name
     * @param Transaction $transaction
     * @param array $config runtime configuration for the driver instead of reading from config file
     *
     * @return mixed
     */
    public function with(string $driver, Transaction $transaction, array $config = [])
    {
        $this->transaction = $transaction;

        if (!empty($config)) {
            $this->runtimeConfig = $config;
        }

        return $this->driver($driver);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Asanpay\Shaparak\Provider\SamanProvider
     */
    protected function createSamanDriver()
    {
        $config = $this->getConfig('saman');

        return $this->buildProvider(
            SamanProvider::class,
            $config
        );
    }


    /**
     * Build a Shaparak provider instance.
     *
     * @param string $provider
     *
     * @param array $config
     *
     * @return Provider
     */
    public function buildProvider($provider, array $config): Provider
    {
        return new $provider(
            $this->transaction,
            $config,
            Arr::get($config, 'mode', config('shaparak.mode', 'production')),
            Arr::get($config, 'httpClientOptions', [])
        );
    }

    /**
     * Get the default driver name.
     *
     * @return string
     *
     * @throws InvalidArgumentException
     */
    public function getDefaultDriver()
    {
        throw new InvalidArgumentException('No Shaparak driver was specified.');
    }

    /**
     * get provider configuration runtime array or config based configuration
     *
     * @param string $driver
     *
     * @return array
     */
    protected function getConfig(string $driver): array
    {
        if (empty($this->runtimeConfig)) {
            return $this->container['config']["shaparak.providers.{$driver}"];
        } else {
            return $this->runtimeConfig;
        }
    }
}
