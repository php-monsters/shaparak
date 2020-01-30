<?php


namespace Asanpay\Shaparak\Provider;

use SoapFault;
use Asanpay\Shaparak\Contracts\Provider as ProviderContract;

class MellatProvider extends AbstractProvider implements ProviderContract
{
    const URL_INQUIRY = 'inquiry';
    const URL_SETTLE  = 'settle';

    protected $refundSupport = true;

    /**
     * @inheritDoc
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
            'terminalId'     => intval($this->getParameters('terminal_id')),
            'userName'       => $this->getParameters('username'),
            'userPassword'   => $this->getParameters('password'),
            'orderId'        => $this->getGatewayOrderId(),
            'amount'         => $this->getAmount(),
            'localDate'      => $this->getParameters('local_date', date('Ymd')),
            'localTime'      => $this->getParameters('local_time', date('His')),
            'additionalData' => strval($this->getParameters('additional_data')),
            'callBackUrl'    => $this->getCallbackUrl(),
            'payerId'        => intval($this->getParameters('payer_id')),
        ];

        try {
            $soapClient = $this->getSoapClient(self::URL_VERIFY);

            $response = $soapClient->bpPayRequest($sendParams);

            if (isset($response->return)) {
                $response = explode(',', $response->return);

                if ($response[0] == 0) {
                    $this->getTransaction()->setGatewayToken($response[1]); // update transaction reference id

                    return $response[1];
                } else {
                    throw new Exception('shaparak::mellat.error_' . strval($response[0]));
                }
            } else {
                throw new Exception('shaparak::shaparak.token_failed');
            }

        } catch (SoapFault $e) {
            throw new Exception('SoapFault: ' . $e->getMessage() . ' #' . $e->getCode(), $e->getCode());
        }
    }

    /**
     * @inheritDoc
     */
    public function getFormParameters(): array
    {
        $token = $this->requestToken();

        return [
            'gateway'    => 'mellat',
            'method'     => 'post',
            'action'     => $this->getUrlFor(self::URL_GATEWAY),
            'parameters' => [
                'RefId' => $token,
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    public function verifyTransaction(): bool
    {
        if ($this->getTransaction()->isReadyForVerify() == false) {
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

        try {

            $sendParams = [
                'terminalId'      => intval($this->getParameters('terminal_id')),
                'userName'        => $this->getParameters('username'),
                'userPassword'    => $this->getParameters('password'),
                'orderId'         => intval($this->getParameters('SaleOrderId')), // same as SaleOrderId
                'saleOrderId'     => intval($this->getParameters('SaleOrderId')),
                'saleReferenceId' => intval($this->getParameters('SaleReferenceId')),
            ];

            $soapClient = $this->getSoapClient(self::URL_VERIFY);

            $response = $soapClient->bpVerifyRequest($sendParams);

            if (isset($response->return)) {
                if ($response->return != '0') {
                    throw new Exception('shaparak::mellat.error_' . strval($response->return));
                } else {
                    $this->getTransaction()->setCardNumber($this->getParameters('CardHolderInfo'));
                    $this->getTransaction()->setVerified(true); // save()

                    return true;
                }
            } else {
                throw new Exception('shaparak::shaparak.verify_failed');
            }

        } catch (SoapFault $e) {
            throw new Exception('SoapFault: ' . $e->getMessage() . ' #' . $e->getCode(), $e->getCode());
        }
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function inquiryTransaction()
    {
        if ($this->getTransaction()->isReadyForInquiry() == false) {
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
            'terminalId'      => intval($this->getParameters('terminal_id')),
            'userName'        => $this->getParameters('username'),
            'userPassword'    => $this->getParameters('password'),
            'orderId'         => intval($this->getParameters('SaleOrderId')), // same as SaleOrderId
            'saleOrderId'     => intval($this->getParameters('SaleOrderId')),
            'saleReferenceId' => intval($this->getParameters('SaleReferenceId')),
        ];

        try {
            $soapClient = $this->getSoapClient(self::URL_INQUIRY);

            $response = $soapClient->bpInquiryRequest($sendParams);

            if (isset($response->return)) {
                return $response->return;
            } else {
                throw new Exception('shaparak::shaparak.inquiry_failed');
            }

        } catch (SoapFault $e) {

            throw new Exception('SoapFault: ' . $e->getMessage() . ' #' . $e->getCode(), $e->getCode());
        }
    }


    /**
     * Send settle request
     *
     * @return bool
     *
     * @throws Exception
     */
    public function settleTransaction(): bool
    {
        if ($this->getTransaction()->isReadyForSettle() == false) {
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
            'terminalId'      => intval($this->getParameters('terminal_id')),
            'userName'        => $this->getParameters('username'),
            'userPassword'    => $this->getParameters('password'),
            'orderId'         => intval($this->getParameters('SaleOrderId')), // same as SaleOrderId
            'saleOrderId'     => intval($this->getParameters('SaleOrderId')),
            'saleReferenceId' => intval($this->getParameters('SaleReferenceId')),
        ];

        try {
            $soapClient = $this->getSoapClient(self::URL_SETTLE);

            $response = $soapClient->bpSettleRequest($sendParams);

            if (isset($response->return)) {
                if ($response->return == '0' || $response->return == '45') {
                    $this->getTransaction()->setSettled();

                    return true;
                } else {
                    throw new Exception('shaparak::mellat.error_' . strval($response->return));
                }
            } else {
                throw new Exception('shaparak::shaparak.invalid_response');
            }

        } catch (SoapFault $e) {
            throw new Exception('SoapFault: ' . $e->getMessage() . ' #' . $e->getCode(), $e->getCode());
        }

    }

    /**
     * @inheritDoc
     */
    public function refundTransaction(): bool
    {
        if ($this->getTransaction()->isReadyForRefund() == false) {
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
                'terminalId'      => intval($this->getParameters('terminal_id')),
                'userName'        => $this->getParameters('username'),
                'userPassword'    => $this->getParameters('password'),
                'orderId'         => intval($this->getParameters('SaleOrderId')), // same as SaleOrderId
                'saleOrderId'     => intval($this->getParameters('SaleOrderId')),
                'saleReferenceId' => intval($this->getParameters('SaleReferenceId')),
            ];

            $soapClient = $this->getSoapClient(self::URL_REFUND);

            $response = $soapClient->bpReversalRequest($sendParams);

            if (isset($response->return)) {
                if ($response->return == '0' || $response->return == '45') {
                    $this->getTransaction()->setRefunded();

                    return true;
                } else {
                    throw new Exception('shaparak::mellat.error_' . strval($response->return));
                }
            } else {
                throw new Exception('shaparak::mellat.errors.invalid_response');
            }

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
                'RefId',
                'ResCode',
                'SaleOrderId',
                'SaleReferenceId',
                'CardHolderInfo',
            ]);
        } catch (\Exception $e) {
            return false;
        }

        if (!empty($this->getParameters('RefId'))) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function getGatewayReferenceId(): string
    {
        $this->checkRequiredActionParameters([
            'RefId',
        ]);

        return $this->getParameters('RefId');
    }


    /**
     * @inheritDoc
     */
    public function getUrlFor(string $action): string
    {
        if ($this->environment == 'production') {
            switch ($action) {
                case self::URL_GATEWAY:
                    {
                        return 'https://bpm.shaparak.ir/pgwchannel/startpay.mellat';
                    }
                default:
                    {
                        return 'https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl';
                    }
            }
        } else {
            switch ($action) {
                case self::URL_GATEWAY:
                    {
                        return 'http://banktest.ir/gateway/mellat/gate';
                    }
                default:
                    {
                        return 'http://banktest.ir/gateway/mellat/ws?wsdl';
                    }
            }
        }
    }
}
