<?php

namespace PhpMonsters\Shaparak\Provider;

use Illuminate\Support\Str;
use PhpMonsters\Shaparak\Contracts\Provider as ProviderContract;
use PhpMonsters\Shaparak\Contracts\Transaction;
use PhpMonsters\Shaparak\Facades\Shaparak;
use ReflectionClass;
use SoapClient;
use SoapFault;

/**
 * Class AbstractProvider
 *
 * @author    Aboozar Ghaffari
 *
 * @version   v1.0
 *
 * @license   https://github.com/php-monsters/shaparak/blob/master/LICENSE
 */
abstract class AbstractProvider implements ProviderContract
{
    public const URL_GATEWAY = 'gateway';

    public const URL_TOKEN = 'token';

    public const URL_VERIFY = 'verify';

    public const URL_REFUND = 'refund';

    public const URL_CANCEL = 'cancel';

    public const URL_MULTIPLEX = 'multiplex';

    protected bool $provideTransactionResult = true;

    /**
     * shaparak operation environment
     */
    protected string $environment;

    /**
     * The custom parameters to be sent with the request.
     */
    protected array $parameters = [];

    protected Transaction $transaction;

    /**
     * specifies whether the gateway supports transaction reverse/refund or not
     */
    protected bool $refundSupport = false;

    /**
     * specifies whether the gateway supports transaction settlement or not
     */
    protected bool $settlementSupport = false;

    /**
     * The custom Guzzle/SoapClient configuration options.
     */
    protected array $httpClientOptions = [];

    /**
     * banktest mock service base url
     */
    protected string $bankTestBaseUrl;

    /**
     * AdapterAbstract constructor.
     *
     * @param  string  $environment  Shaparak module mode
     */
    public function __construct(
        Transaction $transaction,
        array $configs = [],
        string $environment = 'production',
        array $httpClientOptions = []
    ) {
        $this->environment = $environment;
        $this->transaction = $transaction;
        $this->httpClientOptions = $httpClientOptions;
        $this->bankTestBaseUrl = config('shaparak.banktest_base_url');
        $this->setParameters($configs);
    }

    /**
     * {@inheritDoc}
     */
    public function getForm(): \Illuminate\View\View
    {
        $formParameters = $this->getFormParameters();

        return view(
            'shaparak::goto-gate-form',
            array_merge($formParameters, [
                'buttonLabel' => $this->getParameters('submit_label') ?: __('shaparak::shaparak.goto_gate'),
                'autoSubmit' => (bool) $this->getParameters('auto_submit', true),
            ])
        );
    }

    /**
     * {@inheritDoc}
     */
    abstract public function getFormParameters(): array;

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
     */
    public function setParameters(array $parameters = []): ProviderContract
    {
        $parameters = array_change_key_case($parameters);
        $parameters = array_map('trim', $parameters);

        $this->parameters = array_merge($this->parameters, $parameters);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    abstract public function verifyTransaction(): bool;

    /**
     * {@inheritDoc}
     */
    public function settleTransaction(): bool
    {
        // default behavior
        return $this->getTransaction()->setSettled();
    }

    /**
     * {@inheritDoc}
     */
    public function accomplishTransaction(): bool
    {
        // default behavior
        return $this->getTransaction()->setAccomplished();
    }

    public function getTransaction(): Transaction
    {
        return $this->transaction;
    }

    /**
     * {@inheritDoc}
     */
    abstract public function refundTransaction(): bool;

    /**
     * {@inheritDoc}
     */
    abstract public function getGatewayReferenceId(): string;

    /**
     * {@inheritDoc}
     */
    abstract public function canContinueWithCallbackParameters(): bool;

    /**
     * {@inheritDoc}
     */
    public function refundSupport(): bool
    {
        return $this->refundSupport;
    }

    /**
     * {@inheritDoc}
     */
    public function settlementSupport(): bool
    {
        return $this->settlementSupport;
    }

    /**
     * {@inheritDoc}
     */
    public function checkRequiredActionParameters(array $parameters): void
    {
        $parameters = array_map('strtolower', $parameters);

        foreach ($parameters as $parameter) {
            if (! array_key_exists($parameter, $this->parameters) || trim($this->parameters[$parameter]) === '') {
                throw new Exception("Parameters array must have a not null value for key: '$parameter'");
            }
        }
    }

    /**
     * @throws SoapFault|Exception
     */
    protected function getSoapClient(string $action): SoapClient
    {
        $soapOptions = $this->httpClientOptions ? $this->httpClientOptions['soap'] : [];

        // set soap options if require. see shaparak config
        return new SoapClient($this->getUrlFor($action), $soapOptions);
    }

    /**
     * {@inheritDoc}
     */
    abstract public function getUrlFor(string $action): string;

    /**
     * fetches callback url from parameters
     */
    protected function getCallbackUrl(): string
    {
        return Str::is('http*', $this->getParameters('callback_url')) ?
            $this->getParameters('callback_url') :
            $this->getTransaction()->getCallbackUrl();
    }

    /**
     * fetches payable amount of the transaction
     */
    protected function getAmount(): int
    {
        return (is_int($this->getParameters('amount')) && ! empty($this->getParameters('amount'))) ?
            $this->getParameters('amount') :
            $this->getTransaction()->getPayableAmount();
    }

    /**
     * fetches payable amount of the transaction
     */
    protected function getGatewayOrderId(): int
    {
        return (is_int($this->getParameters('order_id')) && ! empty($this->getParameters('order_id'))) ?
            $this->getParameters('order_id') :
            $this->getTransaction()->getGatewayOrderId();
    }

    protected function log(string $message, array $params = [], string $level = 'debug'): void
    {
        $reflect = new ReflectionClass($this);
        $provider = strtolower(str_replace('Provider', '', $reflect->getShortName()));

        $message = $provider.': '.$message;

        Shaparak::log($message, $params, $level);
    }

    public function getTransactionResult(): bool
    {
        return $this->provideTransactionResult;
    }

    public function setCallBackParameters(array $parameters): bool
    {
        return $this->getTransaction()->setCallBackParameters($parameters);
    }
}
