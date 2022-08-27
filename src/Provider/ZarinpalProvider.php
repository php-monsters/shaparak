<?php

namespace PhpMonsters\Shaparak\Provider;

use Illuminate\Support\Facades\Http;

class ZarinpalProvider extends AbstractProvider
{
    protected bool $refundSupport = true;

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function getFormParameters(): array
    {
        $token = $this->requestToken();

        return [
            'gateway' => 'zarinpal',
            'method' => 'GET',
            'action' => $this->getUrlFor(self::URL_GATEWAY) . '/' . $token,
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
            throw new Exception('transaction is not ready for requesting token from payment gateway');
        }

        $this->checkRequiredActionParameters([
            'merchant_id',
        ]);

        $response = Http::retry(3, 100)->acceptJson()->post($this->getUrlFor(self::URL_TOKEN), [
            'merchant_id' => $this->getParameters('merchant_id'),
            'callback_url' => $this->getCallbackUrl(),
            'amount' => $this->getAmount(),
            'description' => $this->getParameters('description')
        ]);

        if ($response->sucessful()) {
            if ((int)$response->json('data.code') === 100) {
                return $response->json('data.authority');
            }

            $this->log($response->json('errors.message'), $response->json('errors'), 'error');
            throw new Exception(
                $response->json('errors.code') . ' ' . $response->json('errors.message')
            );
        }

        throw new Exception('shaparak::shaparak.token_failed');
    }

    /**
     * @inheritDoc
     */
    public function getUrlFor(string $action = null): string
    {
        if ($this->environment === 'production') {
            switch ($action) {
                case self::URL_GATEWAY:
                {
                    return 'https://www.zarinpal.com/pg/StartPay';
                }
                case self::URL_TOKEN :
                {
                    return 'https://api.zarinpal.com/pg/v4/payment/request.json';
                }
                case self::URL_VERIFY :
                {
                    return 'https://api.zarinpal.com/pg/v4/payment/verify.json';
                }
            }
        } else {
            switch ($action) {
                case self::URL_GATEWAY:
                {
                    return $this->bankTestBaseUrl . '/zarinpal/www.zarinpal.com/pg/StartPay';
                }
                case self::URL_TOKEN :
                {
                    return $this->bankTestBaseUrl . '/zarinpal/api.zarinpal.com/pg/v4/payment/request.json';
                }
                case self::URL_VERIFY :
                {
                    return $this->bankTestBaseUrl . '/zarinpal/api.zarinpal.com/pg/v4/payment/verify.json';
                }
            }
        }
        throw new Exception('url destination is not valid!');
    }

    /**
     * @inheritDoc
     */
    public function canContinueWithCallbackParameters(): bool
    {
        try {
            $this->checkRequiredActionParameters([
                'Authority',
                'State',
            ]);
        } catch (\Exception $e) {
            return false;
        }

        return $this->getParameters('State') === 'OK';
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function getGatewayReferenceId(): string
    {
        $this->checkRequiredActionParameters([
            'Authority',
        ]);

        return $this->getParameters('Authority');
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
            'merchant_id',
            'amount',
            'authority',
        ]);

        if ($this->getParameters('State') !== 'OK') {
            throw new Exception('could not verify transaction with callback state: ' . $this->getParameters('State'));
        }

        $response = Http::retry(3, 100)->acceptJson()->post($this->getUrlFor(self::URL_VERIFY), [
            'merchant_id' => $this->getParameters('merchant_id'),
            'authority' => $this->getParameters('authority'),
            'amount' => $this->getAmount(),
        ]);

        if ($response->sucessful()) {
            if ((int)$response->json('data.code') === 100 || (int)$response->json('data.code') === 101) {
                $this->getTransaction()->setVerified(true); // save()
                return true;
            }

            $this->log($response->json('errors.message'), $response->json('errors'), 'error');
            throw new Exception(
                $response->json('errors.code') . ' ' . $response->json('errors.message')
            );
        }

        throw new Exception('shaparak::shaparak.could_not_verify_transaction');
    }

    /**
     * @return bool
     */
    protected function refundTransaction(): bool
    {
        return false;
    }
}
