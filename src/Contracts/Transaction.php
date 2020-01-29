<?php

namespace Asanpay\Shaparak\Contracts;


interface Transaction
{
    /**
     * return the callback url of the transaction process
     *
     * @return string
     */
    public function getCallbackUrl(): string;

    /**
     * set gateway token of transaction
     *
     * @param string $token
     * @param bool $save
     *
     * @return mixed
     */
    public function setGatewayToken(string $token, bool $save = true): bool;

    /**
     * set reference ID of transaction
     *
     * @param string $referenceId
     * @param bool $save
     *
     * @return mixed
     */
    public function setReferenceId(string $referenceId, bool $save = true): bool;

    /**
     * return an order id for the transaction to requesting a payment token from the gateway
     *
     * @return int
     */
    public function getGatewayOrderId(): int;

    /**
     * check if transaction is ready for requesting token from payment gateway or not
     *
     * @return boolean
     */
    public function isReadyForTokenRequest(): bool;

    /**
     * check if transaction is ready for requesting verify method from payment gateway or not
     *
     * @return bool
     */
    public function isReadyForVerify(): bool;

    /**
     * check if transaction is ready for requesting inquiry method from payment gateway or not
     * This feature does not append to all payment gateways
     *
     * @return bool
     */
    public function isReadyForInquiry(): bool;

    /**
     * check if transaction is ready for requesting settlement method from payment gateway or not
     * This feature does not append to all payment gateways.
     * for example in Mellat gateway this method can assume as SETTLE method
     *
     * @return bool
     */
    public function isReadyForSettle(): bool;

    /**
     * check if transaction is ready for requesting refund method from payment gateway or not
     * This feature does not append to all payment gateways
     *
     * @return bool
     */
    public function isReadyForRefund(): bool;

    /**
     * Mark transaction as a verified transaction
     *
     * @param bool $save
     *
     * @return bool
     */
    public function setVerified(bool $save = true): bool;

    /**
     * Mark transaction as a after verified transaction
     * For example SETTLED in Mellat gateway
     *
     * @param bool $save
     *
     * @return bool
     */
    public function setSettled(bool $save = true): bool;

    /**
     * Mark transaction as a paid/successful transaction
     *
     * @param bool $save
     *
     * @return bool
     */
    public function setAccomplished(bool $save = true): bool;

    /**
     * Mark transaction as a refunded transaction
     *
     * @param bool $save
     *
     * @return bool
     */
    public function setRefunded(bool $save = true): bool;

    /**
     * Returns the payable amount af the transaction
     *
     * @return int
     */
    public function getPayableAmount(): int;

    /**
     * save the pan/card number that used for paying the transaction
     * @param string $cardNumber
     * @param bool $save
     *
     * @return bool
     */
    public function setCardNumber(string $cardNumber, bool $save = true): bool;

    /**
     * Set callback parameters from payment gateway
     *
     * @param array $parameters
     * @param bool $save
     *
     * @return bool
     */
    public function setCallBackParameters(array $parameters, bool $save = true): bool;

    /**
     * Set extra values of the transaction. Every key/value pair that you want to bind to the transaction
     *
     * @param string $key
     * @param $value
     * @param bool $save
     *
     * @return bool
     */
    public function setExtra(string $key, $value, bool $save = true): bool;
}
