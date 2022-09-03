<?php

namespace PhpMonsters\Shaparak\Contracts;

use Illuminate\View\View;
use PhpMonsters\Shaparak\Provider\Exception;

interface Provider
{
    /**
     * Determines whether the provider supports reverse transaction
     *
     * @return bool
     */
    function refundSupport(): bool;

    /**
     * @param array $parameters operation parameters
     *
     * @return Provider
     */
    function setParameters(array $parameters = []): Provider;

    /**
     * @param string|null $key
     *
     * @param null $default
     *
     * @return mixed
     */
    function getParameters(string $key = null, $default = null);

    /**
     * return rendered goto gate form
     *
     * @return View
     */
    function getForm(): View;

    /**
     * return parameters that require for generating goto gate form
     *
     * @return array
     */
    function getFormParameters(): array;

    /**
     * get the transaction
     *
     * @return Transaction
     */
    function getTransaction(): Transaction;

    /**
     * verify transaction
     *
     * @return bool
     */
    function verifyTransaction(): bool;

    /**
     * for handling after verify methods like settle in Mellat gateway
     *
     * @return mixed
     */
    function settleTransaction(): bool;

    /**
     * reverse/refund transaction if supported by the provider
     *
     * @return bool
     */
    function refundTransaction(): bool;

    /**
     * fetch bak gateway reference id from callback parameters
     *
     * @return string
     */
    function getGatewayReferenceId(): string;

    /**
     * get the Url of different parts of a payment process of the gateway
     *
     * @param string $action
     *
     * @return string
     * @throws Exception
     *
     */
    function getUrlFor(string $action): string;

    /**
     * Specifies whether it is possible to continue payment process with the return parameters from the bank gateway
     *
     * @return bool
     */
    function canContinueWithCallbackParameters(): bool;

    /**
     * check for required parameters
     *
     * @param array $parameters
     *
     * @throws Exception
     */
    function checkRequiredActionParameters(array $parameters): void;
}
