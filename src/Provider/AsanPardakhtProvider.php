<?php

namespace PhpMonsters\Shaparak\Provider;

use PhpMonsters\Shaparak\Exceptions\RefundException;
use PhpMonsters\Shaparak\Exceptions\RequestTokenException;
use PhpMonsters\Shaparak\Exceptions\SettlementException;
use PhpMonsters\Shaparak\Exceptions\VerificationException;
use SoapFault;

class AsanPardakhtProvider extends AbstractProvider
{
    public const URL_UTILS = 'utils';
    public const URL_STATUS = 'status';
    public const URL_TIME = 'time';
    public const URL_HOST = 'hostinfo';

    protected bool $refundSupport = true;

    /**
     * Prepare data for purchasing invoice
     *
     * @return array
     *
     * @throws \SoapFault
     */
    private function requestTokenData(string $callBackUrl): array
    {
        $this->checkRequiredActionParameters([
            'merchant_id',
            'username',
            'password',
        ]);

        $params = [
            1,
            $this->getParameters('username'),
            $this->getParameters('password'),
            $this->getGatewayOrderId(),
            $this->getAmount(),
            $this->getParameters('local_date', date('Ymd His')),
            (string)$this->getParameters('additional_data'),
            $callBackUrl,
            0,
        ];

        return [
            'merchantConfigurationID' => $this->getParameters('merchant_id'),
            'encryptedRequest'        => $this->encrypt(implode(',', $params)),
        ];
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    protected function requestToken(): string
    {
        $transaction = $this->getTransaction();

        if ($transaction->isReadyForTokenRequest() === false) {
            throw new RequestTokenException(
                'transaction is not ready for requesting token from payment gateway'
            );
        }

        $sendParams = $this->requestTokenData($transaction->getCallbackUrl());

        try {
            $soapClient = $this->getSoapClient();

            $response = $soapClient->RequestOperation($sendParams);
            if (!$response) {
                throw new RequestTokenException('Error in AsanPardakht requestToken');
            }

            if (isset($response->RequestOperationResult)) {
                $response = $response->RequestOperationResult;

                if (is_numeric($response[0]) && (int)$response[0] === 0) {
                    $token = substr($response, 2);
                    $this->getTransaction()->setGatewayToken($token); // update transaction reference id

                    return $token;
                }

                throw new RequestTokenException(
                    sprintf('shaparak::asanpardakht.RequestOperation.error_%s', $response[0])
                );
            }

            throw new RequestTokenException('shaparak::shaparak.token_failed');
        } catch (SoapFault $e) {
            $this->log($e->getMessage(), [], 'error');
            throw new RequestTokenException('SoapFault: ' . $e->getMessage() . ' #' . $e->getCode(), $e->getCode());
        }
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function getFormParameters(): array
    {
        return [
            'gateway'    => 'asanpardakht',
            'method'     => 'post',
            'action'     => $this->getUrlFor(self::URL_GATEWAY),
            'parameters' => [
                'RefId'    => $this->requestToken(),
                'mobileap' => $this->getParameters('user_mobile'),
            ],
        ];
    }

    /**
     * Prepare data for payment verification/settlement
     *
     * @return array
     *
     * @throws \SoapFault
     */
    private function verifyTransactionData(): array
    {
        $this->checkRequiredActionParameters([
            'ReturningParams',
            'username',
            'password',
        ]);

        $encryptedReturningParamsString = $this->getParameters('ReturningParams');
        $returningParamsString          = $this->decrypt($encryptedReturningParamsString);
        $returningParams                = explode(",", $returningParamsString);

        /**
         * other data:
         *   $amount = $returningParams[0];
         *   $saleOrderId = $returningParams[1];
         *   $refId = $returningParams[2];
         *   $resCode = $returningParams[3]
         *   $resMessage = $returningParams[4];
         *   $payGateTranID = $returningParams[5];
         *   $rrn = $returningParams[6];
         *   $lastFourDigitOfPAN = $returningParams[7];
         **/

        $resCode       = $returningParams[3];
        $payGateTranID = $returningParams[5];

        // set card number as request params
        $this->setParameters(['lastFourDigitOfPAN' => $returningParams[7]]);

        if (!is_numeric($resCode) || (int)$resCode !== 0) {
            throw new VerificationException(
                sprintf('shaparak::asanpardakht.Verification.error_%s', $resCode)
            );
        }

        $credentials          = [
            $this->getParameters('username'),
            $this->getParameters('password'),
        ];
        $encryptedCredentials = $this->encrypt(implode(',', $credentials));

        return [
            'merchantConfigurationID' => $this->getParameters('merchant_id'),
            'encryptedCredentials'    => $encryptedCredentials,
            'payGateTranID'           => $payGateTranID,
        ];
    }

    /**
     * @inheritDoc
     * @throws VerificationException|Exception
     */
    public function verifyTransaction(): bool
    {
        if ($this->getTransaction()->isReadyForVerify() === false) {
            throw new Exception('shaparak::shaparak.could_not_verify_transaction');
        }

        try {
            $sendParams = $this->verifyTransactionData();

            $soapClient = $this->getSoapClient(self::URL_VERIFY);

            $response = $soapClient->RequestVerification($sendParams);
            if (!$response) {
                throw new VerificationException('Error in AsanPardakht verifyTransaction');
            }

            if (isset($response->RequestVerificationResult)) {
                $resultCode = (int)$response->RequestVerificationResult;

                if ($resultCode !== 500) {
                    throw new VerificationException(
                        sprintf('shaparak::asanpardakht.Verification.error_%s', $resultCode)
                    );
                }

                $this->getTransaction()->setCardNumber($this->getParameters('lastFourDigitOfPAN'));
                $this->getTransaction()->setVerified(true); // save()

                return true;
            }

            throw new VerificationException('shaparak::shaparak.verify_failed');
        } catch (SoapFault $e) {
            $this->log($e->getMessage(), [], 'error');
            throw new VerificationException('SoapFault: ' . $e->getMessage() . ' #' . $e->getCode(), $e->getCode());
        }
    }

    /**
     * Send settle request
     *
     * @return bool
     *
     * @throws Exception
     * @throws SettlementException
     */
    public function settleTransaction(): bool
    {
        if ($this->getTransaction()->isReadyForSettle() === false) {
            throw new SettlementException('shaparak::shaparak.could_not_settle_payment');
        }

        try {
            $sendParams = $this->verifyTransactionData();

            $soapClient = $this->getSoapClient(self::URL_VERIFY);

            $response = $soapClient->RequestReconciliation($sendParams);
            if (!$response) {
                throw new SettlementException('Error in AsanPardakht verifyTransaction');
            }

            if (isset($response->RequestReconciliationResult)) {
                $resultCode = (int)$response->RequestReconciliationResult;

                if ($resultCode !== 600) {
                    throw new SettlementException(
                        sprintf('shaparak::asanpardakht.Reconciliation.error_%s', $resultCode)
                    );
                }

                $this->getTransaction()->setSettled(true); // save()

                return true;
            }

            throw new SettlementException('shaparak::shaparak.settle_failed');
        } catch (SoapFault $e) {
            $this->log($e->getMessage(), [], 'error');
            throw new SettlementException('SoapFault: ' . $e->getMessage() . ' #' . $e->getCode(), $e->getCode());
        }
    }

    private function refundTransactionData(): array
    {

    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function refundTransaction(): bool
    {
        if ($this->getTransaction()->isReadyForRefund() === false) {
            throw new RefundException('shaparak::shaparak.could_not_refund_payment');
        }

        try {
            $sendParams = $this->verifyTransactionData();

            $soapClient = $this->getSoapClient(self::URL_REFUND);

            $response = $soapClient->CancelVerification($sendParams);
            if (!$response) {
                throw new RefundException('Error in AsanPardakht refundTransaction');
            }

            if (isset($response->CancelVerificationResult)) {
                $resultCode = (int)$response->CancelVerificationResult;

                if ($resultCode !== 700) {
                    throw new RefundException(
                        sprintf('shaparak::asanpardakht.Refund.error_%s', $resultCode)
                    );
                }

                $this->getTransaction()->setRefunded(true); // save()

                return true;
            }

            throw new RefundException('shaparak::shaparak.refund_failed');
        } catch (SoapFault $e) {
            $this->log($e->getMessage(), [], 'error');
            throw new SettlementException('SoapFault: ' . $e->getMessage() . ' #' . $e->getCode(), $e->getCode());
        }
    }

    /**
     * @inheritDoc
     */
    public function canContinueWithCallbackParameters(): bool
    {
        try {
            $this->checkRequiredActionParameters([
                'ReturningParams',
            ]);
        } catch (\Exception $e) {
            return false;
        }

        if (!empty($this->getParameters('ReturningParams'))) {
            return true;
        }

        return false;
    }

    /**
     * @inheritDoc
     * @throws Exception|SoapFault
     */
    public function getGatewayReferenceId(): string
    {
        $this->checkRequiredActionParameters([
            'ReturningParams',
        ]);

        $encryptedReturningParamsString = $this->getParameters('ReturningParams');
        $returningParamsString          = $this->decrypt($encryptedReturningParamsString);
        $returningParams                = explode(",", $returningParamsString);

        return $returningParams[5];
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
                    return 'https://asan.shaparak.ir';
                }
                case self::URL_UTILS:
                {
                    return 'https://ipgsoap.asanpardakht.ir/paygate/internalutils.asmx?wsdl';
                }
                case self::URL_HOST:
                {
                    return 'https://ipgsoap.asanpardakht.ir/utils/hostinfo.asmx?wsdl';
                }
                case self::URL_TIME:
                {
                    return 'https://ipgsoap.asanpardakht.ir/paygate/servertime.asmx?wsdl';
                }
                case self::URL_STATUS:
                {
                    return 'https://ipgsoap.asanpardakht.ir/paygate/statuswatch.asmx?wsdl';
                }
                default:
                {
                    return 'https://ipgsoap.asanpardakht.ir/paygate/merchantservices.asmx?wsdl';
                }
            }
        } else {
            switch ($action) {
                case self::URL_GATEWAY:
                {
                    return 'https://banktest.ir/gateway/asanpardakht/gate';
                }
                case self::URL_UTILS:
                {
                    return 'https://banktest.ir/gateway/asanpardakht/paygate/internalutils.asmx?wsdl';
                }
                case self::URL_HOST:
                {
                    return 'https://banktest.ir/gateway/asanpardakht/utils/hostinfo.asmx?wsdl';
                }
                case self::URL_TIME:
                {
                    return 'https://banktest.ir/gateway/asanpardakht/paygate/servertime.asmx?wsdl';
                }
                case self::URL_STATUS:
                {
                    return 'https://banktest.ir/gateway/asanpardakht/paygate/statuswatch.asmx?wsdl';
                }
                default:
                {
                    return 'https://banktest.ir/gateway/asanpardakht/paygate/merchantservices.asmx?wsdl';
                }
            }
        }
    }

    /**
     * Encrypt given string.
     *
     * @param $string
     *
     * @return mixed
     *
     * @throws \SoapFault|Exception
     */
    protected function encrypt($string)
    {
        $client = $this->getSoapClient(self::URL_UTILS);

        $params = [
            'aesKey'        => $this->getParameters('key'),
            'aesVector'     => $this->getParameters('iv'),
            'toBeEncrypted' => $string,
        ];

        return $client->EncryptInAES($params)->EncryptInAESResult;
    }

    /**
     * Decrypt given string.
     *
     * @param $string
     *
     * @return mixed
     *
     * @throws \SoapFault|Exception
     */
    protected function decrypt($string)
    {
        $client = $this->getSoapClient(self::URL_UTILS);

        $params = [
            'aesKey'        => $this->getParameters('key'),
            'aesVector'     => $this->getParameters('iv'),
            'toBeDecrypted' => $string,
        ];

        return $client->DecryptInAES($params)->DecryptInAESResult;
    }
}
