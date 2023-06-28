<?php

namespace PhpMonsters\Shaparak\Contracts;

use Illuminate\View\View;
use PhpMonsters\Shaparak\Provider\Exception;

interface Provider
{
    /**
     * Determines whether the provider supports reverse transaction
     */
    public function refundSupport(): bool;

    /**
     * @param  array  $parameters operation parameters
     */
    public function setParameters(array $parameters = []): Provider;

    /**
     * @param  null  $default
     * @return mixed
     */
    public function getParameters(string $key = null, $default = null);

    /**
     * return rendered goto gate form
     */
    public function getForm(): View;

    /**
     * return parameters that require for generating goto gate form
     */
    public function getFormParameters(): array;

    /**
     * get the transaction
     */
    public function getTransaction(): Transaction;

    /**
     * verify transaction
     */
    public function verifyTransaction(): bool;

    /**
     * for handling after verify methods like settle in Mellat gateway
     *
     * @return mixed
     */
    public function settleTransaction(): bool;

    /**
     * reverse/refund transaction if supported by the provider
     */
    public function refundTransaction(): bool;

    /**
     * accomplish transaction. It means the transaction is immutable from now on
     */
    public function accomplishTransaction(): bool;

    /**
     * fetch bak gateway reference id from callback parameters
     */
    public function getGatewayReferenceId(): string;

    /**
     * get the Url of different parts of a payment process of the gateway
     *
     *
     * @throws Exception
     */
    public function getUrlFor(string $action): string;

    /**
     * Specifies whether it is possible to continue payment process with the return parameters from the bank gateway
     */
    public function canContinueWithCallbackParameters(): bool;

    /**
     * check for required parameters
     *
     *
     * @throws Exception
     */
    public function checkRequiredActionParameters(array $parameters): void;
}
