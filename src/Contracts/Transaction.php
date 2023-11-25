<?php

namespace PhpMonsters\Shaparak\Contracts;

interface Transaction
{
    /**
     * return the callback url of the transaction process
     */
    public function getCallbackUrl(): string;

    /**
     * set gateway token of transaction
     */
    public function setGatewayToken(string $token, bool $save = true): bool;

    /**
     * get gateway token of transaction
     */
    public function getGatewayToken(): string;

    /**
     * set reference ID of transaction شناسه/کد پیگیری
     */
    public function setReferenceId(string $referenceId, bool $save = true): bool;

    /**
     * get reference ID of the
     */
    public function getReferenceId(): string;

    /**
     * return an order id for the transaction to requesting a payment token from the gateway
     */
    public function getGatewayOrderId(): int;

    /**
     * check if transaction is ready for requesting token from payment gateway or not
     */
    public function isReadyForTokenRequest(): bool;

    /**
     * check if transaction is ready for requesting verify method from payment gateway or not
     */
    public function isReadyForVerify(): bool;

    /**
     * check if transaction is ready for requesting inquiry method from payment gateway or not
     * This feature does not append to all payment gateways
     */
    public function isReadyForInquiry(): bool;

    /**
     * check if transaction is ready for requesting settlement method from payment gateway or not
     * This feature does not append to all payment gateways.
     * for example in Mellat gateway this method can assume as SETTLE method
     */
    public function isReadyForSettle(): bool;

    /**
     * check if transaction is ready for requesting refund method from payment gateway or not
     * This feature does not append to all payment gateways
     */
    public function isReadyForRefund(): bool;

    /**
     * Mark transaction as a verified transaction
     */
    public function setVerified(bool $save = true): bool;

    /**
     * Mark transaction as a after verified transaction
     * For example SETTLED in Mellat gateway
     */
    public function setSettled(bool $save = true): bool;

    /**
     * Mark transaction as a paid/successful transaction
     */
    public function setAccomplished(bool $save = true): bool;

    /**
     * Mark transaction as a refunded transaction
     */
    public function setRefunded(bool $save = true): bool;

    /**
     * Returns the payable amount af the transaction
     */
    public function getPayableAmount(): int;

    /**
     * save the pan/card number that used for paying the transaction
     */
    public function setCardNumber(string $cardNumber, bool $save = false): bool;

    /**
     * Set callback parameters from payment gateway
     */
    public function setCallBackParameters(array $parameters, bool $save = true): bool;

    /**
     * Set extra values of the transaction. Every key/value pair that you want to bind to the transaction
     */
    public function addExtra(string $key, $value, bool $save = true): bool;
}
