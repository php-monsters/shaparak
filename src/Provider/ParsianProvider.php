<?php

namespace PhpMonsters\Shaparak\Provider;

use SoapFault;

class ParsianProvider extends AbstractProvider
{
    public const URL_SALE    = 'sale';
    public const URL_CONFIRM = 'confirm';

    protected bool $refundSupport = true;

    /**
     * @inheritDoc
     * @throws Exception
     */
    protected function requestToken(): string
    {
        $transaction = $this->getTransaction();

        if ($transaction->isReadyForTokenRequest() === false) {
            throw new Exception('transaction is not ready for requesting token from payment gateway');
        }

        $this->checkRequiredActionParameters([
            'pin',
        ]);

        $sendParams = [
            'LoginAccount'   => $this->getParameters('pin'),
            'Amount'         => $transaction->getPayableAmount(),
            'OrderId'        => $transaction->getGatewayOrderId(),
            'CallBackUrl'    => $this->getCallbackUrl(),
            'AdditionalData' => (string)$this->getParameters('additional_date'),
        ];

        try {
            $soapClient = $this->getSoapClient(self::URL_SALE);

            $response = $soapClient->SalePaymentRequest(["requestData" => $sendParams]);

            if (isset($response->SalePaymentRequestResult,
                $response->SalePaymentRequestResult->Status,
                $response->SalePaymentRequestResult->Token)) {
                if ((int)$response->SalePaymentRequestResult->Status === 0) {
                    $this->log("fetched token from gateway: {$response->SalePaymentRequestResult->Token}");
                    $this->getTransaction()->setGatewayToken($response->SalePaymentRequestResult->Token);

                    return $response->SalePaymentRequestResult->Token;
                }

                throw new Exception(sprintf('shaparak::parsian.error_%s',
                    $response->SalePaymentRequestResult->Status));
            }

            throw new Exception('shaparak::shaparak.token_failed');
        } catch (SoapFault $e) {
            throw new Exception('SoapFault: ' . $e->getMessage() . ' #' . $e->getCode(), $e->getCode());
        }
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function getFormParameters(): array
    {
        $token = $this->requestToken();

        return [
            'gateway'    => 'parsian',
            'method'     => 'get',
            'action'     => $this->getUrlFor(self::URL_GATEWAY),
            'parameters' => [
                'token' => $token,
            ],
        ];
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    protected function verifyTransaction(): bool
    {
        if ($this->getTransaction()->isReadyForVerify() === false) {
            throw new Exception('shaparak::shaparak.could_not_verify_transaction');
        }

        $this->checkRequiredActionParameters([
            'pin',
            'Token',
            'Status',
        ]);

        if ((int)$this->getParameters('Status') !== 0) {
            throw new Exception(
                'could not verify transaction with callback state: ' . $this->getParameters('Status')
            );
        }

        try {
            $sendParams = [
                'LoginAccount' => $this->getParameters('pin'),
                'Token'        => $this->getParameters('Token'),
            ];

            $soapClient = $this->getSoapClient(self::URL_CONFIRM);

            $response = $soapClient->ConfirmPayment(["requestData" => $sendParams]);

            if (isset($response->ConfirmPaymentResult, $response->ConfirmPaymentResult->Status)) {
                if ((int)$response->ConfirmPaymentResult->Status === 0) {
                    //$this->getTransaction()->setCardNumber($this->getParameters('CardNumberMasked'), false); // no save()
                    $this->getTransaction()->setVerified(true); // save()

                    return true;
                }

                throw new Exception(
                    sprintf('shaparak::parsian.error_%s', $response->ConfirmPaymentResult->Status)
                );
            }

            throw new Exception('shaparak::shaparak.could_not_verify_transaction');
        } catch (SoapFault $e) {
            throw new Exception('SoapFault: ' . $e->getMessage() . ' #' . $e->getCode(), $e->getCode());
        }
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    protected function refundTransaction(): bool
    {
        if ($this->getTransaction()->isReadyForRefund() === false) {
            throw new Exception('shaparak::shaparak.could_not_refund_payment');
        }

        $this->checkRequiredActionParameters([
            'pin',
            'Token',
        ]);

        try {
            $sendParams = [
                'LoginAccount' => $this->getParameters('pin'),
                'Token'        => $this->getParameters('Token'),
            ];

            $soapClient = $this->getSoapClient(self::URL_REFUND);

            $response = $soapClient->ReversalRequest(["requestData" => $sendParams]);

            if (isset($response->ReversalRequestResult, $response->ReversalRequestResult->Status)) {
                if ((int)$response->ReversalRequestResult->Status === 0) {
                    $this->getTransaction()->setRefunded();

                    return true;
                }

                throw new Exception(
                    sprintf('shaparak::parsian.error_%s', $response->ReversalRequestResult->Status)
                );
            }

            throw new Exception('larapay::parsian.errors.invalid_response');
        } catch (SoapFault $e) {
            throw new Exception('SoapFault: ' . $e->getMessage() . ' #' . $e->getCode(), $e->getCode());
        }
    }

    /**
     * @inheritDoc
     */
    public function canContinueWithCallbackParameters(): bool
    {
        try {
            $this->checkRequiredActionParameters([
                'Token',
                'Status',
            ]);
        } catch (\Exception $e) {
            return false;
        }

        return (int)$this->getParameters('Status') === 0;
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function getGatewayReferenceId(): string
    {
        $this->checkRequiredActionParameters([
            'RRN',
        ]);

        return $this->getParameters('RRN');
    }

    /**
     * @inheritDoc
     */
    public function getUrlFor(string $action): string
    {
        if ($this->environment === 'production') {
            switch ($action) {
                case self::URL_GATEWAY:
                {
                    return 'https://pec.shaparak.ir/NewIPG/';
                }
                case self::URL_SALE :
                {
                    return 'https://pec.shaparak.ir/NewIPGServices/Sale/SaleService.asmx?WSDL';
                }
                case self::URL_CONFIRM :
                {
                    return 'https://pec.shaparak.ir/NewIPGServices/Confirm/ConfirmService.asmx?WSDL';
                }
                case self::URL_REFUND :
                {
                    return 'https://pec.shaparak.ir/NewIPGServices/Reverse/ReversalService.asmx?WSDL';
                }
                case self::URL_MULTIPLEX:
                {
                    return 'https://pec.shaparak.ir/NewIPGServices/MultiplexedSale/OnlineMultiplexedSalePaymentService.asmx?WSDL';
                }
            }
        } else {
            switch ($action) {
                case self::URL_GATEWAY:
                {
                    return 'https://banktest.ir/gateway/Parsian/NewIPGq';
                }
                case self::URL_SALE :
                {
                    return 'https://banktest.ir/gateway/Parsian/NewIPGServices/Sale/SaleService.asmx?WSDL';
                }
                case self::URL_CONFIRM :
                {
                    return 'https://banktest.ir/gateway/Parsian/NewIPGServices/Confirm/ConfirmService.asmx?WSDL';
                }
                case self::URL_REFUND :
                {
                    return 'https://banktest.ir/gateway/Parsian/NewIPGServices/Reverse/ReversalService.asmx?WSDL';
                }
                case self::URL_MULTIPLEX:
                {
                    return 'https://banktest.ir/gateway/Parsian/NewIPGServices/MultiplexedSale/OnlineMultiplexedSalePaymentService.asmx?WSDL';
                }
            }
        }
    }
}
