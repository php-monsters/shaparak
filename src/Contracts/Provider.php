<?php


namespace Asanpay\Shaparak\Contracts;

interface Provider
{
    /**
     * return the provider name
     * @return string
     */
    public function name(): string;

    /**
     * Determines whether the provider supports reverse transaction
     *
     * @return bool
     */
    public function refundSupport(): bool;

    /**
     * @param array $parameters operation parameters
     *
     * @return Provider
     */
    public function setParameters(array $parameters = []): Provider;

    /**
     * @param string|null $key
     *
     * @param null $default
     *
     * @return mixed
     */
    public function getParameters(string $key = null, $default = null);

    /**
     * return rendered goto gate form
     *
     * @return string
     */
    public function generateForm(): string;

    /**
     * return parameters that require for generating goto gate form
     *
     * @return array
     */
    public function getFormParameters(): array;

    /**
     * get the transaction
     *
     * @return Transaction
     */
    public function getTransaction(): Transaction;

    /**
     * verify transaction
     *
     * @return bool
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
     *
     * @return bool
     */
    public function refundTransaction(): bool;

    /**
     * fetch bak gateway reference id from callback parameters
     *
     * @return string
     */
    public function getGatewayReferenceId(): string;

    /**
     * get the Url of different parts of a payment process of the gateway
     *
     * @param string $action
     *
     * @return string
     */
    public function getUrlFor(string $action): string;

    /**
     * Specifies whether it is possible to continue payment process with the return parameters from the bank gateway
     *
     * @return bool
     */
    public function canContinueWithCallbackParameters(): bool;

    /**
     * check for required parameters
     *
     * @param array $parameters
     *
     * @throws Exception
     */
    public function checkRequiredActionParameters(array $parameters): void;
}
