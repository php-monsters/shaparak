<?php

namespace PhpMonsters\Shaparak\Provider;

use PhpMonsters\Shaparak\Facades\Shaparak;
use Samuraee\EasyCurl\EasyCurl;
use Illuminate\Support\Str;
use SoapClient;
use SoapFault;
use PhpMonsters\Shaparak\Contracts\Transaction;
use PhpMonsters\Shaparak\Contracts\Provider as ProviderContract;
use ReflectionClass;

/**
 * Class AbstractProvider
 *
 * @author    Aboozar Ghaffari
 * @copyright 2018 asanpay.com
 * @package   Shaparak
 * @package   PhpMonsters\Shaparak
 * @version   v1.0
 * @license   https://github.com/php-monsters/shaparak/blob/master/LICENSE
 */
abstract class AbstractProvider implements ProviderContract
{
    public const URL_GATEWAY   = 'gateway';
    public const URL_TOKEN     = 'token';
    public const URL_VERIFY    = 'verify';
    public const URL_REFUND    = 'refund';
    public const URL_MULTIPLEX = 'multiplex';

    /**
     * shaparak operation environment
     *
     * @var string
     */
    protected string $environment;

    /**
     * The custom parameters to be sent with the request.
     *
     * @var array
     */
    protected array $parameters = [];

    /**
     * @var Transaction
     */
    protected Transaction $transaction;

    /**
     * specifies whether the gateway supports transaction reverse/refund or not
     * @var bool
     */
    protected bool $refundSupport = false;

    /**
     * The custom Guzzle/SoapClient configuration options.
     *
     * @var array
     */
    protected array $httpClientOptions = [];

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
            'buttonLabel' => $this->getParameters('submit_label') ?: __("shaparak::shaparak.goto_gate"),
            'autoSubmit'  => (bool)$this->getParameters('auto_submit', true),
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
        // default behavior
        return $this->getTransaction()->setSettled(true);
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
            if (!array_key_exists($parameter, $this->parameters) || trim($this->parameters[$parameter]) === '') {
                throw new Exception("Parameters array must have a not null value for key: '$parameter'");
            }
        }
    }

    /**
     * @param string $action
     *
     * @return SoapClient
     * @throws SoapFault|Exception
     */
    protected function getSoapClient(string $action): SoapClient
    {
        $soapOptions = $this->httpClientOptions ? $this->httpClientOptions['soap'] : [];

        // set soap options if require. see shaparak config
        return new SoapClient($this->getUrlFor($action), $soapOptions);
    }

    /**
     * return a Curl Wrapper
     * @return EasyCurl
     */
    protected function getCurl(): EasyCurl
    {
        $httpOptions = $this->httpClientOptions ? $this->httpClientOptions['curl'] : [];
        $curl        = new EasyCurl();
        // set curl options if require. see shaparak config
        if (!empty($httpOptions)) {
            foreach ($httpOptions as $k => $v) {
                $curl->addOption($k, $v);
            }
        }

        return $curl;
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

    protected function log(string $message, array $params = [], string $level = 'debug'): void
    {
        $reflect  = new ReflectionClass($this);
        $provider = strtolower(str_replace('Provider', '', $reflect->getShortName()));

        $message = $provider . ": " . $message;

        Shaparak::log($message, $params, $level);
    }
}
