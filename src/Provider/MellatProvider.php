<?php

namespace PhpMonsters\Shaparak\Provider;

use SoapFault;

class MellatProvider extends AbstractProvider
{
    public const URL_INQUIRY = 'inquiry';

    public const URL_SETTLE = 'settle';

    protected bool $refundSupport = true;

    protected bool $settlementSupport = true;

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
            'username',
            'password',
        ]);

        $sendParams = [
            'terminalId' => (int) $this->getParameters('terminal_id'),
            'userName' => $this->getParameters('username'),
            'userPassword' => $this->getParameters('password'),
            'orderId' => $this->getGatewayOrderId(), // get it from Transaction
            'amount' => $this->getAmount(),
            'localDate' => $this->getParameters('local_date', date('Ymd')),
            'localTime' => $this->getParameters('local_time', date('His')),
            'additionalData' => (string) $this->getParameters('additional_data'),
            'callBackUrl' => $this->getCallbackUrl(),
            'payerId' => (int) $this->getParameters('payer_id'),
        ];

        try {
            $soapClient = $this->getSoapClient(self::URL_TOKEN);

            $response = $soapClient->bpPayRequest($sendParams);

            if (isset($response->return)) {
                $response = explode(',', $response->return);

                if ((int) $response[0] === 0) {
                    $this->getTransaction()->setGatewayToken($response[1]); // update transaction

                    return $response[1];
                }

                throw new Exception(sprintf('shaparak::mellat.error_%s', $response[0]));
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
        return [
            'gateway' => 'mellat',
            'method' => 'post',
            'action' => $this->getUrlFor(self::URL_GATEWAY),
            'parameters' => [
                'RefId' => $this->requestToken(),
                'MobileNo' => $this->getParameters('user_mobile'),
            ],
        ];
    }

    protected function getGatewayOrderIdFromCallBackParameters(): string
    {
        return (string) $this->getParameters('SaleOrderId');
    }

    protected function callbackAbuseCheckList(): void
    {
        if (!(
            (string)$this->getTransaction()->gateway_order_id === $this->getGatewayOrderIdFromCallBackParameters()
            && (int)$this->getParameters('FinalAmount') === $this->getTransaction()->getPayableAmount()
            && (string)$this->getParameters('refID') === $this->getTransaction()->getGatewayToken()
        )) {
            throw new Exception('shaparak::shaparak.could_not_pass_abuse_checklist');
        }
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
            'username',
            'password',
            'RefId',
            'ResCode',
            'SaleOrderId',
            'SaleReferenceId',
            'CardHolderInfo',
            'CardHolderPan',
        ]);

        $this->callbackAbuseCheckList();

        try {

            $sendParams = [
                'terminalId' => (int) $this->getParameters('terminal_id'),
                'userName' => $this->getParameters('username'),
                'userPassword' => $this->getParameters('password'),
                'orderId' => (int) $this->getParameters('SaleOrderId'), // same as SaleOrderId
                'saleOrderId' => (int) $this->getParameters('SaleOrderId'),
                'saleReferenceId' => (int) $this->getParameters('SaleReferenceId'),
            ];


            $soapClient = $this->getSoapClient(self::URL_VERIFY);

            $response = $soapClient->bpVerifyRequest($sendParams);

            if (isset($response->return)) {
                if ((int) $response->return !== 0) {
                    throw new Exception(sprintf('shaparak::mellat.error_%s', $response->return));
                }

                $this->getTransaction()->setCardNumber($this->getParameters('CardHolderPan'));
                $this->getTransaction()->setVerified(true); // save()

                return true;
            }

            throw new Exception('shaparak::shaparak.verify_failed');
        } catch (SoapFault $e) {
            throw new Exception('SoapFault: '.$e->getMessage().' #'.$e->getCode(), $e->getCode());
        }
    }

    /**
     * @return string
     *
     * @throws Exception
     */
    public function inquiryTransaction() :string
    {
        if ($this->getTransaction()->isReadyForInquiry() === false) {
            throw new Exception('shaparak::shaparak.could_not_inquiry_payment');
        }

        $this->checkRequiredActionParameters([
            'terminal_id',
            'username',
            'password',
            'RefId',
            'ResCode',
            'SaleOrderId',
            'SaleReferenceId',
            'CardHolderInfo',
        ]);

        $sendParams = [
            'terminalId' => (int) $this->getParameters('terminal_id'),
            'userName' => $this->getParameters('username'),
            'userPassword' => $this->getParameters('password'),
            'orderId' => (int) $this->getParameters('SaleOrderId'), // same as SaleOrderId
            'saleOrderId' => (int) $this->getParameters('SaleOrderId'),
            'saleReferenceId' => (int) $this->getParameters('SaleReferenceId'),
        ];

        try {
            $soapClient = $this->getSoapClient(self::URL_INQUIRY);

            $response = $soapClient->bpInquiryRequest($sendParams);

            if (isset($response->return)) {
                return $response->return;
            }

            throw new Exception('shaparak::shaparak.inquiry_failed');
        } catch (SoapFault $e) {

            throw new Exception('SoapFault: '.$e->getMessage().' #'.$e->getCode(), $e->getCode());
        }
    }

    /**
     * Send settle request
     *
     *
     * @throws Exception
     */
    public function settleTransaction(): bool
    {
        if ($this->getTransaction()->isReadyForSettle() === false) {
            throw new Exception('shaparak::shaparak.could_not_settle_payment');
        }

        $this->checkRequiredActionParameters([
            'terminal_id',
            'username',
            'password',
            'RefId',
            'ResCode',
            'SaleOrderId',
            'SaleReferenceId',
            'CardHolderInfo',
        ]);

        $sendParams = [
            'terminalId' => (int) $this->getParameters('terminal_id'),
            'userName' => $this->getParameters('username'),
            'userPassword' => $this->getParameters('password'),
            'orderId' => (int) $this->getParameters('SaleOrderId'), // same as SaleOrderId
            'saleOrderId' => (int) $this->getParameters('SaleOrderId'),
            'saleReferenceId' => (int) $this->getParameters('SaleReferenceId'),
        ];

        try {
            $soapClient = $this->getSoapClient(self::URL_SETTLE);

            $response = $soapClient->bpSettleRequest($sendParams);

            if (isset($response->return) && is_numeric($response->return)) {
                if ((int) $response->return === 0 || (int) $response->return === 45) {
                    $this->getTransaction()->setSettled(true);

                    return true;
                }

                throw new Exception(sprintf('shaparak::mellat.error_%s', $response->return));
            }

            throw new Exception('shaparak::shaparak.invalid_response');
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
        if ($this->getTransaction()->isReadyForRefund() === false) {
            throw new Exception('shaparak::shaparak.could_not_refund_payment');
        }

        $this->checkRequiredActionParameters([
            'terminal_id',
            'username',
            'password',
            'RefId',
            'ResCode',
            'SaleOrderId',
            'SaleReferenceId',
            'CardHolderInfo',
        ]);

        try {
            $sendParams = [
                'terminalId' => (int) $this->getParameters('terminal_id'),
                'userName' => $this->getParameters('username'),
                'userPassword' => $this->getParameters('password'),
                'orderId' => (int) $this->getParameters('SaleOrderId'), // same as SaleOrderId
                'saleOrderId' => (int) $this->getParameters('SaleOrderId'),
                'saleReferenceId' => (int) $this->getParameters('SaleReferenceId'),
            ];

            $soapClient = $this->getSoapClient(self::URL_REFUND);

            $response = $soapClient->bpReversalRequest($sendParams);

            if (isset($response->return) && is_numeric($response->return)) {
                if ((int) $response->return === 0 || (int) $response->return === 45) {
                    $this->getTransaction()->setRefunded(true);

                    return true;
                }

                throw new Exception('shaparak::mellat.error_'. $response->return);
            }

            throw new Exception('shaparak::mellat.errors.invalid_response');
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
                'RefId',
                'ResCode',
                'SaleOrderId',
                'SaleReferenceId',
                'CardHolderInfo',
            ]);
        } catch (\Exception $e) {
            return false;
        }

        if (! empty($this->getParameters('RefId'))) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    public function getGatewayReferenceId(): string
    {
        $this->checkRequiredActionParameters([
            'RefId',
        ]);

        return $this->getParameters('RefId');
    }

    /**
     * {@inheritDoc}
     */
    public function getUrlFor(string $action): string
    {
        if ($this->environment === 'production') {
            return match ($action) {
                self::URL_GATEWAY => 'https://bpm.shaparak.ir/pgwchannel/startpay.mellat',
                default => 'https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl',
            };
        }

        return match ($action) {
            self::URL_GATEWAY => $this->bankTestBaseUrl . '/mellat/bpm.shaparak.ir/pgwchannel/startpay.mellat',
            default => $this->bankTestBaseUrl . '/mellat/bpm.shaparak.ir/pgwchannel/services/pgw?wsdl',
        };
    }
}
