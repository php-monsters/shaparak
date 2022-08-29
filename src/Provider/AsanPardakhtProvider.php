<?php

namespace PhpMonsters\Shaparak\Provider;

use Illuminate\Support\Facades\Http;
use PhpMonsters\Shaparak\Exceptions\RequestTokenException;
use PhpMonsters\Shaparak\Exceptions\SettlementException;
use PhpMonsters\Shaparak\Exceptions\VerificationException;

class AsanPardakhtProvider extends AbstractProvider
{
    public const URL_RESULT = 'result';
    public const GET_METHOD = 'get';
    public const POST_METHOD = 'post';
    public const URL_SETTLEMENT = 'settlement';

    protected bool $refundSupport = true;

    /**
     * @return array
     * @throws Exception
     * @throws RequestTokenException
     */
    public function getFormParameters(): array
    {
        return [
            'gateway' => 'asanpardakht',
            'method' => self::POST_METHOD,
            'action' => $this->getUrlFor(self::URL_GATEWAY),
            'parameters' => [
                'RefId' => $this->requestToken(),
                'mobileap' => $this->getParameters('user_mobile'),
            ],
        ];
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
                case self::URL_TOKEN:
                {
                    return 'https://ipgrest.asanpardakht.ir/v1/Token';
                }
                case self::URL_VERIFY:
                {
                    return 'https:///ipgrest.asanpardakht.ir/v1/Verify';
                }
                case self::URL_RESULT:
                {
                    return 'https:///ipgrest.asanpardakht.ir/v1/TranResult';
                }
                case self::URL_SETTLEMENT:
                {
                    return 'https:///ipgrest.asanpardakht.ir/v1/Settlement';
                }
            }
        } else {
            switch ($action) {
                case self::URL_GATEWAY:
                {
                    return $this->bankTestBaseUrl . '/ap/asan.shaparak.ir';
                }
                case self::URL_TOKEN:
                {
                    return $this->bankTestBaseUrl . '/ap/ipgrest.asanpardakht.ir/v1/Token';
                }
                case self::URL_VERIFY:
                {
                    return $this->bankTestBaseUrl . '/ap/ipgrest.asanpardakht.ir/v1/Verify';
                }
                case self::URL_RESULT:
                {
                    return $this->bankTestBaseUrl . '/ap/ipgrest.asanpardakht.ir/v1/TranResult';
                }
                case self::URL_SETTLEMENT:
                {
                    return $this->bankTestBaseUrl . '/ap/ipgrest.asanpardakht.ir/v1/Settlement';
                }
            }
        }
        throw new Exception("could not find url for {$action} action");
    }

    /**
     * @return string
     * @throws Exception
     * @throws RequestTokenException
     */
    protected function requestToken(): string
    {
        $transaction = $this->getTransaction();

        if ($transaction->isReadyForTokenRequest() === false) {
            throw new RequestTokenException(
                'transaction is not ready for requesting token from payment gateway'
            );
        }

        $response = $this->sendParamToAp(
            $this->requestTokenData(),
            $this->getUrlFor(self::URL_TOKEN),
            self::POST_METHOD
        );

        if ($response->successful() && !empty($response->body())) {
            $this->getTransaction()->setGatewayToken(
                $response->body(),
                true
            );

            return $response->body();
        } elseif ($response->status() !== 200) {
            //todo: handle error page
            throw new Exception(sprintf('shaparak::asanpardakht.error_%s', $response->status()));
        }

        throw new Exception('shaparak::shaparak.token_failed');
    }

    /**
     * @return array
     * @throws Exception
     */
    private function requestTokenData(): array
    {
        $this->checkRequiredActionParameters([
            'terminal_id',
            'username',
            'password',
            'local_date',
            'local_time',
        ]);

        return [
            'serviceTypeId' => 1,
            'merchantConfigurationId' => $this->getParameters('terminal_id'),
            'localInvoiceId' => $this->getGatewayOrderId(),
            'amountInRials' => $this->getAmount(),
            'localDate' => $this->getParameters('local_date') . ' ' . $this->getParameters('local_time'),
            'callbackURL' => $this->getCallbackUrl(),
            'paymentId' => 0,
        ];
    }

    /**
     * @param array $params
     * @param string $url
     * @param string $method
     * @return mixed
     */
    public function sendParamToAp(array $params, string $url, string $method): mixed
    {
        return Http::acceptJson()->withHeaders([
            'usr' => $this->getParameters('username'),
            'pwd' => $this->getParameters('password'),
        ])->$method(
            $url,
            $params
        );
    }

    /**
     * @inheritDoc
     * @throws VerificationException|Exception
     */
    public function verifyTransaction(): bool
    {
        // required parameters will be checked by getTransactionResult method
        $this->getTransactionResult();

        if ($this->getTransaction()->isReadyForVerify() === false) {
            throw new Exception('shaparak::shaparak.could_not_verify_transaction');
        }

        try {
            $response = $this->sendParamToAp(
                [
                    'localInvoiceId' => $this->getTransaction()->getGatewayOrderId(),
                    'merchantConfigurationId' => $this->getParameters('terminal_id'),
                ],
                $this->getUrlFor(self::URL_VERIFY),
                self::POST_METHOD
            );

            if ($response->successful() && $response->status() === 200) {
                $this->getTransaction()->setVerified(true); // save()
                return true;
            }

            throw new VerificationException(
                sprintf('shaparak::asanpardakht.Verification.error_%s', $response->status())
            );
        } catch (\Exception $e) {
            $this->log($e->getMessage(), [], 'error');
            throw new VerificationException(
                'verifyTransaction: ' . $e->getMessage() . ' #' . $e->getCode(),
                $e->getCode()
            );
        }
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function getTransactionResult(): bool
    {
        $this->checkRequiredActionParameters([
            'username',
            'password',
            'PayGateTranID',
            'terminal_id',
        ]);

        $response = $this->sendParamToAp([
            'localInvoiceId' => $this->getTransaction()->getGatewayOrderId(),
            'merchantConfigurationId' => $this->getParameters('terminal_id'),
        ], $this->getUrlFor(self::URL_RESULT), self::GET_METHOD);

        if ($response->successful() && $response->status() === 200 && !empty($response->body())) {
            $this->getTransaction()->setCallBackParameters($response->json(), false);
            $this->getTransaction()->setReferenceId($response->json('refID'));

            return true;
        } else {
            //todo: handle error page
            throw new Exception(sprintf('shaparak::asanpardakhtRest.error_%s', $response->status()));
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
        $this->checkRequiredActionParameters([
            'username',
            'password',
            'PayGateTranID',
            'terminal_id',
        ]);

        if ($this->getTransaction()->isReadyForSettle() === false) {
            throw new SettlementException('shaparak::shaparak.could_not_settle_payment');
        }

        try {
            $response = $this->sendParamToAp(      [
                'payGateTranId' => $this->getParameters('payGateTranID'),
                'MerchantConfigurationId' => $this->getParameters('terminal_id'),
            ], $this->getUrlFor(self::URL_SETTLEMENT), self::POST_METHOD);

            if ($response->successful() && $response->status() === 200) {
                $this->getTransaction()->setSettled(true); // save()
                return true;
            }

            throw new SettlementException(
                sprintf('shaparak::asanpardakht.Verification.error_%s', $response->status())
            );
        } catch (\Exception $e) {
            $this->log($e->getMessage(), [], 'error');
            throw new SettlementException(
                'settleTransaction: ' . $e->getMessage() . ' #' . $e->getCode(),
                $e->getCode()
            );
        }
    }


    /**
     * @inheritDoc
     * @throws Exception
     */
    public function refundTransaction(): bool
    {
    }

    /**
     * @inheritDoc
     */
    public function canContinueWithCallbackParameters(): bool
    {
        try {
            $this->checkRequiredActionParameters([
                'ReturningParams',
                'localInvoiceID',
            ]);
        } catch (\Exception $e) {
            return false;
        }

        if (!empty($this->getParameters('ReturningParams'))) {
            return true;
        }

        return false;
    }

    public function getGatewayReferenceId(): string
    {
        if (!isset($this->getTransaction()->gateway_ref_id)) {
            return $this->getParameters('saleOrderId');
        }

        return $this->getTransaction()->gateway_ref_id;
    }
}
