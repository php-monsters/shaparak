<?php


namespace Asanpay\Shaparak\Provider;

use Asanpay\Shaparak\Contracts\Provider as ProviderContract;

class MelliProvider extends AbstractProvider implements ProviderContract
{
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
            'merchant_id',
            'transaction_key',
        ]);

        $terminalId = $this->getParameters('terminal_id');
        $amount     = $this->getAmount();
        $key        = $this->getParameters('transaction_key');
        $orderId    = $this->getGatewayOrderId();

        $signature = $this->encryptPKCS7("{$terminalId};{$orderId};{$amount}", "{$key}");

        $sendParams = [
            'TerminalId'    => $terminalId,
            'MerchantId'    => $this->getParameters('merchant_id'),
            'Amount'        => $amount,
            'SignData'      => $signature,
            'ReturnUrl'     => $this->getCallbackUrl(),
            'LocalDateTime' => date("m/d/Y g:i:s a"),
            'OrderId'       => $orderId,
        ];

        $jsonData = json_encode($sendParams);
        $curl     = $this->getCurl();

        $curl->addHeader('Content-Type', 'application/json');
        // and then
        $response = $curl->rawPost($this->getUrlFor(self::URL_TOKEN), $jsonData);
        $info     = $curl->getTransferInfo();

        if ($info['http_code'] == 200 && !empty($response)) {
            $token = json_decode(trim($response));

            if ($response->ResCode == 0) {// got string token
                $transaction->setGatewayToken($token, true); // update transaction reference id

                return $token;
            } else {
                throw new Exception(strval($response->ResCode) . ' :: ' . @$response->Description);
            }
        } else {
            throw new Exception('shaparak::shaparak.token_failed');
        }

    }

    /**
     * @inheritDoc
     */
    public function getFormParameters(): array
    {
        $token = $this->requestToken();

        return [
            'gateway'    => 'melli',
            'method'     => 'get',
            'action'     => $this->getUrlFor(self::URL_GATEWAY),
            'parameters' => [
                'Token' => $token,
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
            'merchant_id',
            'transaction_key',
            'OrderId',
            'Token',
            'ResCode',
        ]);

        if ($this->getParameters('ResCode') != 0) {
            throw new Exception('could not verify transaction with callback state: ' . $this->getParameters('State'));
        }

        $key   = $this->getParameters('transaction_key');
        $token = $this->getParameters('Token');

        $signature = $this->encryptPKCS7($token, $key);

        $sendParams = [
            'Token'    => $token,
            'SignData' => $signature,
        ];

        $jsonData = json_encode($sendParams);
        $curl     = $this->getCurl();

        $curl->addHeader('Content-Type', 'application/json');
        // and then
        $response = $curl->rawPost($this->getUrlFor(self::URL_VERIFY), $jsonData);
        $info     = $curl->getTransferInfo();

        if ($info['http_code'] == 200 && !empty($response)) {

            $response = json_decode(trim($response));

            if ($response->ResCode == 0 && $response->Amount == $this->getAmount()) {// got string token
                foreach ($response as $k => $v) {
                    $this->getTransaction()->addExtra($k, $v, false);
                }
                $this->getTransaction()->setCardNumber($this->getParameters('accNoVal', @$response->accNoVal), false);
                $this->getTransaction()->save();

                return true;
            } else {
                throw new Exception(strval($response->ResCode) . ' :: ' . @$response->Description);
            }
        } else {
            throw new Exception('shaparak::shaparak.could_not_verify_transaction');
        }

    }

    public function refundTransaction(): bool
    {
        // TODO: Implement refundTransaction() method.
        throw new Exception('melli gateway does not support refund action');
    }

    /**
     * @inheritDoc
     */
    public function canContinueWithCallbackParameters(): bool
    {
        try {
            $this->checkRequiredActionParameters([
                'OrderId',
                'Token',
                'ResCode',
            ]);
        } catch (\Exception $e) {
            return false;
        }

        if ($this->getParameters('ResCode') == 0) {
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
            'Token',
        ]);

        return $this->getParameters('Token');
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
                        return 'https://sadad.shaparak.ir/VPG/Purchase';
                    }
                case self::URL_TOKEN :
                    {
                        return 'https://sadad.shaparak.ir/vpg/api/v0/Request/PaymentRequest';
                    }
                case self::URL_VERIFY :
                    {
                        return 'https://sadad.shaparak.ir/vpg/api/v0/Advice/Verify';
                    }
            }
        } else {
            switch ($action) {
                case self::URL_GATEWAY:
                    {
                        return 'https://sadad.shaparak.ir/VPG/Purchase';
                    }
                case self::URL_TOKEN :
                    {
                        return 'http://banktest.ir/gateway/melli/payment-request';
                    }
                case self::URL_VERIFY :
                    {
                        return 'http://banktest.ir/gateway/melli/verify';
                    }
            }
        }

        throw new Exception("could not find url for {$action} action");
    }

    /**
     * Create sign data based on (Tripledes(ECB,PKCS7)) algorithm
     *
     * @param string data
     * @param string key
     *
     * @return string
     */
    protected function encryptPKCS7(string $str, string $key): string
    {
        $key        = base64_decode($key);
        $cipherText = openssl_encrypt($str, "DES-EDE3", $key, OPENSSL_RAW_DATA);

        return base64_encode($cipherText);
    }
}
