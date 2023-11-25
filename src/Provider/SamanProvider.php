<?php

namespace PhpMonsters\Shaparak\Provider;

use SoapFault;

class SamanProvider extends AbstractProvider
{
    protected bool $refundSupport = true;

    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    protected function requestToken(): string
    {
        $transaction = $this->getTransaction();

        if ($transaction->isReadyForTokenRequest() === false) {
            throw new Exception('transaction is not ready for requesting token from payment gateway');
        }

        $this->checkRequiredActionParameters([
            'terminal_id',
        ]);

        $sendParams = [
            'TermID' => $this->getParameters('terminal_id'),
            'ResNum' => $this->getGatewayOrderId(),
            'TotalAmount' => $this->getAmount(),
        ];

        try {
            $soapClient = $this->getSoapClient(self::URL_TOKEN);

            $response = $soapClient->__soapCall('RequestToken', $sendParams);

            if (! empty($response)) {
                $token = trim($response);
                if (strlen($token) >= 20) { // got string token
                    $this->log("fetched token from gateway: {$token}");
                    $transaction->setGatewayToken($token, true); // update transaction reference id

                    return $token;
                }

                throw new Exception(sprintf('shaparak::saman.error_%s', $response));
            }

            throw new Exception('shaparak::shaparak.token_failed');
        } catch (SoapFault $e) {
            throw new Exception('SoapFault: '.$e->getMessage().' #'.$e->getCode(), $e->getCode());
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    public function getFormParameters(): array
    {
        $token = $this->requestToken();

        return [
            'gateway' => 'saman',
            'method' => 'POST',
            'action' => $this->getUrlFor(self::URL_GATEWAY),
            'parameters' => [
                'Token' => $token,
                'RedirectURL' => $this->getCallbackUrl(),
                'getmethod' => (bool) $this->getParameters('get_method', true),
            ],
        ];
    }

    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    public function verifyTransaction(): bool
    {
        if ($this->getTransaction()->isReadyForVerify() === false) {
            throw new Exception('shaparak::shaparak.could_not_verify_transaction');
        }

        $this->checkRequiredActionParameters([
            'terminal_id',
            'State',
            'StateCode',
            'RefNum',
            'ResNum',
            'TraceNo',
            'SecurePan',
            'CID',
        ]);

        if ($this->getParameters('State') !== 'OK') {
            throw new Exception('could not verify transaction with callback state: '.$this->getParameters('State'));
        }

        try {
            $soapClient = $this->getSoapClient(self::URL_VERIFY);

            $response = $soapClient->VerifyTransaction(
                $this->getParameters('RefNum'),
                $this->getParameters('terminal_id')
            );

            if (isset($response)) {
                if ($response > 0 && ($response - $this->getTransaction()->getPayableAmount() < PHP_FLOAT_EPSILON)) {
                    // double-check the amount by transaction amount
                    $this->getTransaction()->setCardNumber($this->getParameters('SecurePan'), false); // no save()
                    $this->getTransaction()->setVerified(true); // save()

                    return true;
                }

                throw new Exception('shaparak::saman.error_'.(string) $response);
            }

            throw new Exception('shaparak::shaparak.could_not_verify_transaction');
        } catch (SoapFault $e) {
            throw new Exception('SoapFault: '.$e->getMessage().' #'.$e->getCode(), $e->getCode());
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    public function refundTransaction(): bool
    {
        if ($this->refundSupport === false || $this->getTransaction()->isReadyForRefund() === false) {
            throw new Exception('shaparak::shaparak.could_not_refund_payment');
        }

        $this->checkRequiredActionParameters([
            'RefNum',
            'terminal_id',
            'terminal_pass',
        ]);

        try {
            $soapClient = $this->getSoapClient(self::URL_REFUND);

            $refundAmount = $this->getAmount(); // total amount

            $response = $soapClient->reverseTransaction1(
                $this->getParameters('RefNum'),
                $this->getParameters('terminal_id'),
                $this->getParameters('terminal_pass'),
                $refundAmount
            );

            if (isset($response)) {
                if ($response == 1) { // check by transaction amount
                    $this->getTransaction()->setRefunded(true);

                    return true;
                }

                throw new Exception('shaparak::saman.error_'.strval($response));
            }

            throw new Exception('shaparak::shaparak.could_not_refund_payment');
        } catch (SoapFault $e) {
            throw new Exception('SoapFault: '.$e->getMessage().' #'.$e->getCode(), $e->getCode());
        }
    }

    /**
     * {@inheritDoc}
     */
    public function canContinueWithCallbackParameters(): bool
    {
        try {
            $this->checkRequiredActionParameters([
                'RefNum',
                'State',
            ]);
        } catch (\Exception $e) {
            return false;
        }

        return $this->getParameters('State') === 'OK';
    }

    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    public function getGatewayReferenceId(): string
    {
        $this->checkRequiredActionParameters([
            'RefNum',
        ]);

        return $this->getParameters('RefNum');
    }

    /**
     * {@inheritDoc}
     */
    public function getUrlFor(string $action = null): string
    {
        if ($this->environment === 'production') {
            return match ($action) {
                self::URL_GATEWAY => 'https://sep.shaparak.ir/Payment.aspx',
                self::URL_TOKEN => 'https://sep.shaparak.ir/Payments/InitPayment.asmx?WSDL',
                default => 'https://sep.shaparak.ir/payments/referencepayment.asmx?WSDL',
            };
        }

        return match ($action) {
            self::URL_GATEWAY => $this->bankTestBaseUrl . '/saman/sep.shaparak.ir/payment.aspx',
            self::URL_TOKEN => $this->bankTestBaseUrl . '/saman/sep.shaparak.ir/payments/initpayment.asmx?wsdl',
            default => $this->bankTestBaseUrl . '/saman/sep.shaparak.ir/payments/referencepayment.asmx?wsdl',
        };
    }
}
