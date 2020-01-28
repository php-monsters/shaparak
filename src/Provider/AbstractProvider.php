<?php

namespace Asanpay\Shaparak\Provider;

use SoapClient;
use SoapFault;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Asanpay\Shaparak\Contracts\Transaction;
use Illuminate\Support\Facades\Log;
use Asanpay\Shaparak\Contracts\Provider as ProviderContract;

/**
 * Class AbstractProvider
 *
 * @author    Aboozar Ghaffari
 * @package   Shaparak
 * @copyright 2018 asanpay.com
 * @package   Asanpay\Shaparak
 * @version   v1.0
 * @license   https://github.com/asanpay/shaparak/blob/master/LICENSE
 */
abstract class AbstractProvider implements ProviderContract
{
    const URL_GATEWAY = 'gateway';
    const URL_TOKEN   = 'token';
    const URL_VERIFY  = 'verify';
    const URL_REFUND  = 'refund';

    const NAME = 'AbstractProvider';

    /**
     * shaparak operation environment
     *
     * @var string
     */
    protected $environment;

    /**
     * The HTTP request instance.
     *
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * @var
     */
    protected $urls = [];

    /**
     * The custom parameters to be sent with the request.
     *
     * @var array
     */
    protected $parameters = [];

    /**
     * @var array
     */
    protected $soapOptions = [];

    /**
     * @var Transaction
     */
    protected $transaction;

    /**
     * specifies if gateway supports transaction reverse or not
     * @var bool
     */
    protected $refundSupport = false;

    /**
     * The HTTP Client instance.
     *
     * @var \GuzzleHttp\Client
     */
    protected $httpClient;

    /**
     * The custom Guzzle/SoapClient configuration options.
     *
     * @var array
     */
    protected $httpClientOptions = [];


    /**
     * AdapterAbstract constructor.
     *
     * @param Transaction $transaction
     * @param array $configs
     * @param string $environment Shaparak module mode
     * @param array $httpClientOptions
     */
    public function __construct(
        Transaction $transaction,
        array $configs = [],
        string $environment,
        array $httpClientOptions = []
    ) {
        $this->environment = $environment;
        $this->transaction = $transaction;
        $this->httpClientOptions = $httpClientOptions;
        $this->setParameters($configs);
    }

    public function name(): string
    {
        return self::NAME;
    }
    /**
     * @inheritDoc
     */
    abstract public function generateForm(): string;

    /**
     * @inheritDoc
     */
    abstract public function verifyTransaction(): bool;

    /**
     * @inheritDoc
     */
    public function settleTransaction(): bool {
        return true; // just some of gateways needed this method implementation
    }

    /**
     * @inheritDoc
     */
    abstract public function refundTransaction(): bool;

    /**
     * @inheritDoc
     */
    abstract public function getGatewayReferenceId(): string;

    /**
     * @inheritDoc
     */
    abstract public function getUrlFor(string $action): string;

    /**
     * @inheritDoc
     */
    abstract public function canContinueWithCallbackParameters(): bool;


    /**
     * @inheritDoc
     */
    public function refundSupport(): bool
    {
        return $this->refundSupport;
    }

    /**
     * @inheritDoc
     */
    public function setParameters(array $parameters = []): ProviderContract
    {
        $parameters = array_change_key_case($parameters, CASE_LOWER);
        $parameters = array_map('trim', $parameters);

        $this->parameters = array_merge($this->parameters, $parameters);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getParameters(string $key = null, $default = null)
    {
        if (is_null($key)) {
            return $this->parameters;
        }

        $key = strtolower($key);

        return $this->parameters[$key] ?? $default;
    }

    /**
     * @inheritDoc
     */
    abstract public function getFormParameters(): array;

    /**
     * @return Transaction
     */
    public function getTransaction(): Transaction
    {
        return $this->transaction;
    }

    /**
     * @inheritDoc
     */
    public function checkRequiredActionParameters(array $parameters): void
    {
        foreach ($parameters as $parameter) {
            $parameter = strtolower($parameter);

            if (!array_key_exists($parameter, $this->parameters) || empty($this->parameters[$parameter])) {
                throw new Exception("Parameters array must have a not null value for key: '$parameter'");
            }
        }
    }

    /**
     * @param string $action
     *
     * @return SoapClient
     * @throws SoapFault
     */
    protected function getSoapClient(string $action): SoapClient
    {
        $soapOptions = $this->httpClientOptions ?? [];
        return new SoapClient($this->getUrlFor($action), $soapOptions);
    }

    /**
     * @param string $string
     *
     * @return string
     */
    protected function slugify(string $string):string
    {
        return trim(str_replace('-', '_', $string));
    }

    /**
     * object to array
     *
     * @param $object
     *
     * @return array
     */
    protected function obj2array($object): array
    {
        return json_decode(json_encode($object), true);
    }
}
