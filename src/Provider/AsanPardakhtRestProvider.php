<?php

namespace Asanpay\Shaparak\Provider;

use Asanpay\Shaparak\Exceptions\RequestTokenException;
use Asanpay\Shaparak\Exceptions\SettlementException;
use Asanpay\Shaparak\Exceptions\VerificationException;
use Illuminate\Support\Facades\Http;

class AsanPardakhtRestProvider extends AbstractProvider
{
    public const URL_UTILS = 'utils';
    public const URL_RESULT = 'result';
    public const URL_STATUS = 'status';
    public const URL_TIME = 'time';
    public const URL_HOST = 'hostinfo';
    public const GET_METHOD = 'get';
    public const POST_METHOD = 'post';
    public const URL_SETTLEMENT = 'settlement';

    protected bool $refundSupport = true;


    /**
     * @param string $callBackUrl
     * @return array
     * @throws Exception
     */
    private function requestTokenData(string $callBackUrl): array
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
            'callbackURL' => $callBackUrl . '/?localInvoiceId=' . $this->getTransaction()->gateway_order_id,
            'paymentId' => 0,
        ];
    }

    /**
     * @return array|mixed
     * @throws Exception
     */
    public function getTransactionResult(): mixed
    {
        $url = $this->getUrlFor(self::URL_RESULT);
        $params = [
            'localInvoiceId' => $this->getTransaction()->gateway_order_id,
            'merchantConfigurationId' => $this->getParameters('terminal_id'),
        ];
        $response = $this->sendParamToAp($params, $url, self::GET_METHOD);

        if ($response->status() === 200 && !empty($response)) {
            $response = json_decode($response->body(), true);

            $this->getTransaction()->setCallBackParameters($response);
            $this->getTransaction()->setReferenceId($response['refID']);

            return $this->getTransaction()->gateway_ref_id;
        } elseif ($response->status() !== 200) {
            //todo: handle error page
            throw new Exception(sprintf('shaparak::asanpardakhtRest.error_%s', $response->status()));
        }
//        throw new Exception('shaparak::shaparak.token_failed');
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

        $params = $this->requestTokenData($this->getCallbackUrl());

        $response = $this->sendParamToAp($params, $this->getUrlFor(self::URL_TOKEN), self::POST_METHOD);

        if ($response->status() === 200 && !empty($response)) {
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
     * @return array
     * @throws Exception
     */
    public function transactionData(): array
    {
        $transactionResult = $this->getTransaction()->gateway_callback_params;

        $payGateTranID = $transactionResult['payGateTranID'];

        return [
            'payGateTranId' => $payGateTranID,
            'MerchantConfigurationId' => $this->getParameters('terminal_id'),
        ];
    }

    /**
     * @inheritDoc
     * @throws VerificationException|Exception
     */
    public function verifyTransaction(): bool
    {
        $this->checkRequiredActionParameters([
            'ReturningParams',
            'username',
            'password',
        ]);

        $url = $this->getUrlFor(self::URL_VERIFY);

        if ($this->getTransaction()->isReadyForVerify() === false) {
            throw new Exception('shaparak::shaparak.could_not_verify_transaction');
        }
        $params = $this->transactionData();

        try {
            $response = $this->sendParamToAp($params, $url, self::POST_METHOD);

            if (!empty($response->status())) {
                $resultCode = $response->status();

                if ($resultCode !== 200) {
                    throw new VerificationException(
                        sprintf('shaparak::asanpardakht.Verification.error_%s', $resultCode)
                    );
                }

                $this->getTransaction()->setCardNumber($this->getParameters('cardNumber'));
                $this->getTransaction()->setVerified(true); // save()

                return true;
            }
            throw new VerificationException('shaparak::shaparak.verify_failed');
        } catch (\Exception $e) {
            $this->log($e->getMessage(), [], 'error');
            throw new VerificationException(
                'verifyTransaction: ' . $e->getMessage() . ' #' . $e->getCode(),
                $e->getCode()
            );
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
        $url = $this->getUrlFor(self::URL_SETTLEMENT);

        if ($this->getTransaction()->isReadyForSettle() === false) {
            throw new SettlementException('shaparak::shaparak.could_not_settle_payment');
        }

        $params = $this->transactionData();

        try {
            $response = $this->sendParamToAp($params, $url, self::POST_METHOD);

            if (!empty($response->status())) {
                $resultCode = $response->status();
                //Todo: check this condition

                if ($resultCode !== 200) {
                    throw new SettlementException(
                        sprintf('shaparak::asanpardakht.Verification.error_%s', $resultCode)
                    );
                }

                $this->getTransaction()->setSettled(true); // save()

                return true;
            }
            //throw new VerificationException('shaparak::shaparak.verify_failed');
        } catch (\Exception $e) {
            $this->log($e->getMessage(), [], 'error');
            throw new SettlementException(
                'settleTransaction: ' . $e->getMessage() . ' #' . $e->getCode(),
                $e->getCode()
            );
        }
    }


    /**
     * @param array $params
     * @param string $url
     * @param string $method
     * @return mixed
     */
    public function sendParamToAp(array $params, string $url, string $method): mixed
    {
        return Http::withHeaders([
            'usr' => $this->getParameters('username'),
            'pwd' => $this->getParameters('password'),
        ])->$method(
            $url,
            $params
        );
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
            return $this->getTransactionResult();
        }

        return $this->getTransaction()->gateway_ref_id;
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

                default:
                {
                    return '';
                }
            }
        } else {
            switch ($action) {
                case self::URL_GATEWAY:
                {
                    return 'https://beta.banktest.ir/ap/asan.shaparak.ir';
                }
                case self::URL_TOKEN:
                {
                    return 'https://beta.banktest.ir/ap/ipgrest.asanpardakht.ir/v1/Token';
                }
                case self::URL_VERIFY:
                {
                    return 'https://beta.banktest.ir/ap/ipgrest.asanpardakht.ir/v1/Verify';
                }
                case self::URL_RESULT:
                {
                    return 'https://beta.banktest.ir/ap/ipgrest.asanpardakht.ir/v1/TranResult';
                }
                case self::URL_SETTLEMENT:
                {
                    return 'https://beta.banktest.ir/ap/ipgrest.asanpardakht.ir/v1/Settlement';
                }
                default:
                {
                    return 'https://beta.banktest.ir';
                }
            }
        }
    }


}
