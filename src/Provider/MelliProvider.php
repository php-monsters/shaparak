<?php

namespace PhpMonsters\Shaparak\Provider;

use Illuminate\Support\Facades\Http;

class MelliProvider extends AbstractProvider
{
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
            'merchant_id',
            'transaction_key',
        ]);

        $terminalId = $this->getParameters('terminal_id');
        $amount = $this->getAmount();
        $key = $this->getParameters('transaction_key');
        $orderId = $this->getGatewayOrderId();

        $response = Http::acceptJson()
            ->throw()
            ->post($this->getUrlFor(self::URL_TOKEN), [
                'TerminalId' => $terminalId,
                'MerchantId' => $this->getParameters('merchant_id'),
                'Amount' => $amount,
                'SignData' => $this->encryptPKCS7("{$terminalId};{$orderId};{$amount}", "{$key}"),
                'ReturnUrl' => $this->getCallbackUrl(),
                'LocalDateTime' => date('m/d/Y g:i:s a'),
                'OrderId' => $orderId,
            ]);

        $resCode = $response->json('ResCode');
        if (is_numeric($resCode) && (int) $resCode === 0) {
            $transaction->setGatewayToken($response->json('Token'), true); // update transaction reference id

            return $response->json('Token');
        }

        throw new Exception('shaparak::shaparak.token_failed');
    }

    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    public function getFormParameters(): array
    {
        return [
            'gateway' => 'melli',
            'method' => 'get',
            'action' => $this->getUrlFor(self::URL_GATEWAY),
            'parameters' => [
                'Token' => $this->requestToken(),
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
            'merchant_id',
            'transaction_key',
            'OrderId',
            'Token',
            'ResCode',
        ]);

        if ((int) $this->getParameters('ResCode') !== 0) {
            throw new Exception('could not verify transaction with ResCode: '.$this->getParameters('ResCode'));
        }

        $key = $this->getParameters('transaction_key');
        $token = $this->getParameters('Token');

        $signature = $this->encryptPKCS7($token, $key);

        $response = Http::acceptJson()
            ->throw()
            ->post($this->getUrlFor(self::URL_VERIFY), [
                'Token' => $token,
                'SignData' => $signature,
            ]);

        $resCode = $response->json('ResCode');
        if (is_numeric($resCode) && (int) $resCode === 0 && (int) $response->json('Amount') === $this->getAmount()) {// got string token
            foreach ($response->json() as $k => $v) {
                $this->getTransaction()->addExtra($k, $v, false);
            }
            //$this->getTransaction()->setCardNumber($this->getParameters('accNoVal', @$response->accNoVal), false);
            $this->getTransaction()->setVerified(true);

            return true;
        }

        throw new Exception('shaparak::shaparak.could_not_verify_transaction');
    }

    /**
     * @throws Exception
     */
    public function refundTransaction(): bool
    {
        // TODO: Implement refundTransaction() method.
        throw new Exception('melli gateway does not support refund action right now');
    }

    /**
     * {@inheritDoc}
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

        return (int) $this->getParameters('ResCode') === 0;
    }

    /**
     * {@inheritDoc}
     */
    public function getGatewayReferenceId(): string
    {
        $this->checkRequiredActionParameters([
            'Token',
        ]);

        return $this->getParameters('Token');
    }

    /**
     * {@inheritDoc}
     */
    public function getUrlFor(string $action = null): string
    {
        if ($this->environment === 'production') {
            switch ($action) {
                case self::URL_GATEWAY:

                    return 'https://sadad.shaparak.ir/VPG/Purchase';

                case self::URL_TOKEN:

                    return 'https://sadad.shaparak.ir/vpg/api/v0/Request/PaymentRequest';

                case self::URL_VERIFY:

                    return 'https://sadad.shaparak.ir/vpg/api/v0/Advice/Verify';

            }
        } else {
            switch ($action) {
                case self::URL_GATEWAY:

                    return $this->bankTestBaseUrl.'/melli/sadad.shaparak.ir/VPG/Purchase';

                case self::URL_TOKEN:

                    return $this->bankTestBaseUrl.'/melli/sadad.shaparak.ir/VPG/api/v0/Request/PaymentRequest';

                case self::URL_VERIFY:

                    return $this->bankTestBaseUrl.'/melli/sadad.shaparak.ir/VPG/api/v0/Advice/Verify';

            }
        }

        throw new Exception("could not find url for {$action} action");
    }

    /**
     * Create sign data based on (Tripledes(ECB,PKCS7)) algorithm
     *
     * @param string data
     * @param string key
     */
    protected function encryptPKCS7(string $str, string $key): string
    {
        $key = base64_decode($key);
        $cipherText = openssl_encrypt($str, 'DES-EDE3', $key, OPENSSL_RAW_DATA);

        return base64_encode($cipherText);
    }
}
