<?php

namespace PhpMonsters\Shaparak;

use Illuminate\Support\Arr;
use Illuminate\Support\Manager;
use InvalidArgumentException;
use PhpMonsters\Shaparak\Contracts\Provider;
use PhpMonsters\Shaparak\Contracts\Transaction;
use PhpMonsters\Shaparak\Provider\AsanPardakhtProvider;
use PhpMonsters\Shaparak\Provider\MellatProvider;
use PhpMonsters\Shaparak\Provider\MelliProvider;
use PhpMonsters\Shaparak\Provider\ParsianProvider;
use PhpMonsters\Shaparak\Provider\PasargadProvider;
use PhpMonsters\Shaparak\Provider\SaderatProvider;
use PhpMonsters\Shaparak\Provider\SamanProvider;
use PhpMonsters\Shaparak\Provider\ZarinpalProvider;

class ShaparakManager extends Manager implements Contracts\Factory
{
    /**
     * runtime driver configuration
     *
     * @var array
     */
    protected array $runtimeConfig;

    /**
     * transaction which should paid on the gateway
     *
     * @var Transaction $transaction
     */
    protected Transaction $transaction;

    /**
     * @param  string  $message
     * @param  array  $params
     * @param  string  $level
     */
    public static function log(string $message, array $params = [], string $level = 'debug'): void
    {
        $message = "SHAPARAK -> ".$message;

        forward_static_call(['PhpMonsters\Log\Facades\XLog', $level], $message, $params);
    }

    /**
     * Get a driver instance.
     *
     * @param  string  $driver  driver name
     * @param  Transaction  $transaction
     * @param  array  $config  runtime configuration for the driver instead of reading from config file
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
     * Create an instance of the specified driver.
     *
     * @return Provider
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
     * get provider configuration runtime array or config based configuration
     *
     * @param  string  $driver
     *
     * @return array
     */
    protected function getConfig(string $driver): array
    {
        if (empty($this->runtimeConfig)) {
            return $this->container['config']["shaparak.providers.{$driver}"];
        }

        return $this->runtimeConfig;
    }

    /**
     * Build a Shaparak provider instance.
     *
     * @param  string  $provider
     *
     * @param  array  $config
     *
     * @return Provider
     */
    public function buildProvider(string $provider, array $config): Provider
    {
        return new $provider(
            $this->transaction,
            $config,
            Arr::get($config, 'mode', config('shaparak.mode', 'production')),
            Arr::get($config, 'httpClientOptions', [])
        );
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return Provider
     */
    protected function createParsianDriver()
    {
        $config = $this->getConfig('parsian');

        return $this->buildProvider(
            ParsianProvider::class,
            $config
        );
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return Provider
     */
    protected function createPasargadDriver()
    {
        $config = $this->getConfig('pasargad');

        return $this->buildProvider(
            PasargadProvider::class,
            $config
        );
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return Provider
     */
    protected function createMellatDriver()
    {
        $config = $this->getConfig('mellat');

        return $this->buildProvider(
            MellatProvider::class,
            $config
        );
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return Provider
     */
    protected function createMelliDriver()
    {
        $config = $this->getConfig('melli');

        return $this->buildProvider(
            MelliProvider::class,
            $config
        );
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return Provider
     */
    protected function createSaderatDriver()
    {
        $config = $this->getConfig('saderat');

        return $this->buildProvider(
            SaderatProvider::class,
            $config
        );
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return Provider
     */
    protected function createAsanPardakhtDriver()
    {
        $config = $this->getConfig('asanpardakht');

        return $this->buildProvider(
            AsanPardakhtProvider::class,
            $config
        );
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return Provider
     */
    protected function createZarinpalDriver()
    {
        $config = $this->getConfig('zarinpal');

        return $this->buildProvider(
            ZarinpalProvider::class,
            $config
        );
    }
}
