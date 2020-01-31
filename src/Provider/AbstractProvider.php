<?php

namespace Asanpay\Shaparak\Provider;

use Asanpay\Shaparak\Helper\CurlWrapper;
use Illuminate\Support\Str;
use SoapClient;
use SoapFault;
use Asanpay\Shaparak\Contracts\Transaction;
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

    /**
     * shaparak operation environment
     *
     * @var string
     */
    protected $environment;

    /**
     * The custom parameters to be sent with the request.
     *
     * @var array
     */
    protected $parameters = [];

    /**
     * The SOAP client Client instance.
     *
     * @var SoapClient
     */
    protected $soapClient;

    /**
     * @var Transaction
     */
    protected $transaction;

    /**
     * specifies whether the gateway supports transaction reverse/refund or not
     * @var bool
     */
    protected $refundSupport = false;

    /**
     * The HTTP Client instance.
     *
     * @var CurlWrapper
     */
    protected $curlClient;

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
        string $environment = 'production',
        array $httpClientOptions = []
    ) {
        $this->environment       = $environment;
        $this->transaction       = $transaction;
        $this->httpClientOptions = $httpClientOptions;
        $this->setParameters($configs);
    }

    /**
     * @inheritDoc
     */
    abstract public function getFormParameters(): array;

    /**
     * @inheritDoc
     */
    public function getForm(): string
    {
        $formParameters = $this->getFormParameters();

        return view('shaparak::goto-gate-form', array_merge($formParameters, [
            'buttonLabel' => $this->getParameters('submit_label') ?
                $this->getParameters('submit_label') :
                __("shaparak::shaparak.goto_gate"),
            'autoSubmit'  => boolval($this->getParameters('auto_submit', true)),
        ]));
    }

    /**
     * @inheritDoc
     */
    abstract public function verifyTransaction(): bool;

    /**
     * @inheritDoc
     */
    public function settleTransaction(): bool
    {
        $this->getTransaction()->setSettled(true);
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
        $parameters = array_map('strtolower', $parameters);
        foreach ($parameters as $parameter) {
            if (!array_key_exists($parameter, $this->parameters) || trim($this->parameters[$parameter]) == '') {
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
        $soapOptions = $this->httpClientOptions ? $this->httpClientOptions['soap'] : [];
        // set soap options if require. see shaparak config
        return new SoapClient($this->getUrlFor($action), $soapOptions);
    }

    /**
     * @param string $action
     *
     * @return SoapClient
     * @throws SoapFault
     */
    protected function getCurl(): CurlWrapper
    {
        $httpOptions = $this->httpClientOptions ? $this->httpClientOptions['curl'] : [];
        $curl = new CurlWrapper();
        // set curl options if require. see shaparak config
        if (!empty($httpOptions)) {
            foreach ($httpOptions as $k => $v) {
                $curl->addOption($k, $v);
            }
        }
        return $curl;
    }

    /**
     * @param string $string
     *
     * @return string
     */
    protected function slugify(string $string): string
    {
        return trim(str_replace('-', '_', $string));
    }

    /**
     * fetches callback url from parameters
     * @return string
     */
    protected function getCallbackUrl(): string
    {
        return Str::is('http*', $this->getParameters('callback_url')) ?
            $this->getParameters('callback_url') :
            $this->getTransaction()->getCallbackUrl();
    }

    /**
     * fetches payable amount of the transaction
     * @return int
     */
    protected function getAmount(): int
    {
        return (is_int($this->getParameters('amount')) && !empty($this->getParameters('amount'))) ?
            $this->getParameters('amount') :
            $this->getTransaction()->getPayableAmount();
    }

    /**
     * fetches payable amount of the transaction
     * @return int
     */
    protected function getGatewayOrderId(): int
    {
        return (is_int($this->getParameters('order_id')) && !empty($this->getParameters('order_id'))) ?
            $this->getParameters('order_id') :
            $this->getTransaction()->getGatewayOrderId();
    }
}
