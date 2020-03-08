<?php


namespace Asanpay\Shaparak\Provider;

use SoapFault;
use Asanpay\Shaparak\Contracts\Provider as ProviderContract;

class SamanProvider extends AbstractProvider implements ProviderContract
{
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
        ]);

        $sendParams = [
            'TermID'      => $this->getParameters('terminal_id'),
            'ResNum'      => $this->getGatewayOrderId(),
            'TotalAmount' => $this->getAmount(),
        ];

        try {
            $soapClient = $this->getSoapClient(self::URL_TOKEN);

            $response = $soapClient->__soapCall('RequestToken', $sendParams);

            if (!empty($response)) {
                $token = trim($response);
                if (strlen($token) >= 20) { // got string token
                    $this->log("fetched token from gateway: {$token}");
                    $transaction->setGatewayToken($token, true); // update transaction reference id

                    return $token;
                } else {
                    throw new Exception('shaparak::saman.error_' . strval($response));
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
            'gateway' => 'saman',
            'method'  => 'POST',
            'action'  => $this->getUrlFor(self::URL_GATEWAY),
            'parameters' => [
                'Token'   => $token,
                'RedirectURL' => $this->getCallbackUrl(),
            ]
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
            'State',
            'StateCode',
            'RefNum',
            'ResNum',
            'TraceNo',
            'SecurePan',
            'CID',
        ]);

        if ($this->getParameters('State') != 'OK') {
            throw new Exception('could not verify transaction with callback state: ' . $this->getParameters('State'));
        }

        try {
            $soapClient = $this->getSoapClient(self::URL_VERIFY);

            $response = $soapClient->VerifyTransaction(
                $this->getParameters('RefNum'),
                $this->getParameters('terminal_id')
            );

            if (isset($response)) {
                if ($response == $this->getTransaction()->getPayableAmount()) {
                    // double check the amount by transaction amount
                    $this->getTransaction()->setCardNumber($this->getParameters('SecurePan'), false); // no save()
                    $this->getTransaction()->setVerified(true); // save()

                    return true;
                } else {
                    throw new Exception('shaparak::saman.error_' . strval($response));
                }
            } else {
                throw new Exception('shaparak::shaparak.could_not_verify_transaction');
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
        if ($this->refundSupport == false || $this->getTransaction()->isReadyForRefund() == false) {
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
                } else {
                    throw new Exception('shaparak::saman.error_' . strval($response));
                }
            } else {
                throw new Exception('shaparak::shaparak.could_not_refund_payment');
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
                'RefNum',
                'State',
            ]);
        } catch (\Exception $e) {
            return false;
        }

        if ($this->getParameters('State') == 'OK') {
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
            'RefNum',
        ]);

        return $this->getParameters('RefNum');
    }


    /**
     * @inheritDoc
     */
    public function getUrlFor(string $action = null): string
    {
        if ($this->environment == 'production') {
            switch ($action) {
                case self::URL_GATEWAY:
                    {
                        return 'https://sep.shaparak.ir/Payment.aspx';
                    }
                case self::URL_TOKEN :
                    {
                        return 'https://sep.shaparak.ir/Payments/InitPayment.asmx?WSDL';
                    }
                default:
                    {
                        return 'https://sep.shaparak.ir/payments/referencepayment.asmx?WSDL';
                    }
            }
        } else {
            switch ($action) {
                case self::URL_GATEWAY:
                    {
                        return 'http://banktest.ir/gateway/saman/gate';
                    }
                case self::URL_TOKEN :
                    {
                        return 'http://banktest.ir/gateway/saman/Payments/InitPayment?wsdl';
                    }
                default:
                    {
                        return 'http://banktest.ir/gateway/saman/payments/referencepayment?wsdl';
                    }
            }
        }
    }
}
