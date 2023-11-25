<?php

namespace PhpMonsters\Shaparak\Provider;

use Illuminate\Support\Facades\Http;
use PhpMonsters\Shaparak\Helper\Pasargad\RSAKeyType;
use PhpMonsters\Shaparak\Helper\Pasargad\RSAProcessor;

class PasargadProvider extends AbstractProvider
{
    public const URL_CHECK = 'check';

    public const UPDATE_SUBPAYMENT = 'UpdateInvoiceSubPayment';

    public const GET_SUBPAYMENT = 'GetSubPaymentsReport';

    protected const ACTION_GET_TOKEN = 1003;

    protected bool $refundSupport = true;

    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    public function getFormParameters(): array
    {
        $this->checkRequiredActionParameters([
            'terminal_id',
            'merchant_id',
            'certificate_path',
        ]);

        return [
            'gateway' => 'pasargad',
            'method' => 'GET',
            'action' => $this->getUrlFor(self::URL_GATEWAY),
            'parameters' => [
                'n' => $this->requestToken(),
            ],
        ];
    }

    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    public function requestToken(): string
    {
        $this->log(__METHOD__);
        $transaction = $this->getTransaction();

        if ($transaction->isReadyForTokenRequest() === false) {
            throw new Exception('transaction is not ready for requesting token from payment gateway');
        }

        $this->checkRequiredActionParameters([
            'terminal_id',
            'merchant_id',
            'certificate_path',
        ]);

        $response = $this->callApi($this->getUrlFor(self::URL_TOKEN), [
            'invoicenumber' => $this->getGatewayOrderId(),
            'invoiceDate' => date('Y/m/d H:i:s', strtotime($this->getTransaction()->created_at)),
            'amount' => $this->getAmount(),
            'terminalCode' => $this->getParameters('terminal_id'),
            'merchantCode' => $this->getParameters('merchant_id'),
            'redirectAddress' => $this->getCallbackUrl(),
            'timeStamp' => date('Y/m/d H:i:s'),
            'action' => self::ACTION_GET_TOKEN,
            'subpaymentlist' => $this->getParameters('subpaymentlist'),
        ]);

        $this->log(__METHOD__, $response);

        return $response['Token'];
    }

    /**
     * {@inheritDoc}
     * @throws Exception
     */
    public function verifyTransaction(): bool
    {
        $this->log(__METHOD__);

        if ($this->getTransaction()->isReadyForVerify() === false) {
            throw new Exception('shaparak::shaparak.could_not_verify_transaction');
        }

        $this->checkRequiredActionParameters([
            'terminal_id',
            'merchant_id',
            'certificate_path',
            'iN',
            'iD',
            'tref',
        ]);

        // update transaction reference number
        if (! empty($this->getParameters('tref'))) {
            $this->getTransaction()->setGatewayToken(
                $this->getParameters('tref'),
                true
            ); // update transaction reference id
        } else {
            throw new Exception('could not verify transaction without tref');
        }

        $response = $this->callApi($this->getUrlFor(self::URL_VERIFY), [
            'merchantCode' => $this->getParameters('merchant_id'),
            'terminalCode' => $this->getParameters('terminal_id'),
            'invoiceNumber' => $this->getParameters('iN'),
            'invoiceDate' => $this->getParameters('iD'),
            'amount' => $this->getAmount(),
            'timeStamp' => date('Y/m/d H:i:s'),
        ]);

        $this->log(__METHOD__, $response);

        if ($response['IsSuccess'] === true) {
            $this->getTransaction()->setCardNumber($response['MaskedCardNumber'], false); // no save()
            $this->getTransaction()->addExtra('HashedCardNumber', $response['HashedCardNumber'], false);
            $this->getTransaction()->addExtra('ShaparakRefNumber', $response['ShaparakRefNumber'], false);
            $this->getTransaction()->setVerified();

            return true;
        }

        throw new Exception('shaparak::shaparak.verify_failed');
    }

    /**
     * {@inheritDoc}
     * @throws Exception
     */
    public function refundTransaction(): bool
    {
        $this->log(__METHOD__);

        if ($this->getTransaction()->isReadyForRefund() === false) {
            throw new Exception('shaparak::shaparak.could_not_refund_transaction');
        }

        $this->checkRequiredActionParameters([
            'terminal_id',
            'merchant_id',
            'certificate_path',
            'iN',
            'iD',
            'tref',
        ]);

        $response = $this->callApi($this->getUrlFor(self::URL_REFUND), [
            'invoiceNumber' => $this->getParameters('iN'),
            'invoiceDate' => $this->getParameters('iD'),
            'terminalCode' => $this->getParameters('terminal_id'),
            'merchantCode' => $this->getParameters('merchant_id'),
            'timeStamp' => date('Y/m/d H:i:s'),
        ]);

        $this->log(__METHOD__, $response);

        if ($response['IsSuccess'] === true) {
            $this->getTransaction()->setRefunded();

            return true;
        }

        throw new Exception('shaparak::shaparak.refund_failed');
    }

    private function callApi(string $url, array $body, $method = 'post'): array
    {
        $sign = $this->sign(json_encode($body));

        $this->log("callApi({$url}) Sign: {$sign}", $body);

        $response = Http::contentType('application/json')
            ->acceptJson()
            ->withHeaders([
                'Sign' => $sign,
            ])
            ->throw()
            ->{$method}($url, $body);

        $this->log($response->json('Message'), $response->json());

        if ($response->json('IsSuccess') === false) {
            throw new Exception(($response->json('Message') ?? 'Invalid Pasargad response!'));
        }

        return $response->json();
    }

    private function sign(string $string): string
    {
        $string = sha1($string, true);
        $string = $this->getProcessor()->sign($string); // digital signature

        return base64_encode($string); // base64_encode
    }

    /**
     * @param string|null $certificatePath
     * @return RSAProcessor
     */
    private function getProcessor(string $certificatePath = null): RSAProcessor
    {
        $path = $certificatePath ?: $this->getParameters('certificate_path');

        return new RSAProcessor($path, RSAKeyType::XMLFile);
    }

    /**
     * {@inheritDoc}
     */
    public function canContinueWithCallbackParameters(): bool
    {
        try {
            $this->checkRequiredActionParameters([
                'iN',
                'iD',
                'tref',
            ]);
        } catch (\Exception $e) {
            return false;
        }

        if (! empty($this->getParameters('tref'))) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function getGatewayReferenceId(): string
    {
        $this->checkRequiredActionParameters([
            'tref',
        ]);

        return $this->getParameters('tref');
    }

    /**
     * {@inheritDoc}
     */
    public function getUrlFor(string $action): string
    {
        if ($this->environment === 'production') {
            switch ($action) {
                case self::URL_GATEWAY:

                    return 'https://pep.shaparak.ir/gateway.aspx';

                case self::URL_TOKEN:

                    return 'https://pep.shaparak.ir/Api/v1/Payment/GetToken';

                case self::URL_CHECK:

                    return 'https://pep.shaparak.ir/Api/v1/Payment/CheckTransactionResult';

                case self::URL_VERIFY:

                    return 'https://pep.shaparak.ir/Api/v1/Payment/VerifyPayment';

                case self::URL_REFUND:

                    return 'https://pep.shaparak.ir/Api/v1/Payment/RefundPayment';

                case self::UPDATE_SUBPAYMENT:

                    return 'https://pep.shaparak.ir/Api/v1/Payment/UpdateInvoiceSubPayment';

                case self::GET_SUBPAYMENT:

                    return 'https://pep.shaparak.ir/Api/v1/Payment/GetSubPaymentsReport';

            }
        } else {
            switch ($action) {
                case self::URL_GATEWAY:

                    return $this->bankTestBaseUrl.'/pasargad/pep.shaparak.ir/gateway.aspx';

                case self::URL_TOKEN:

                    return $this->bankTestBaseUrl.'/pasargad/pep.shaparak.ir/Api/v1/Payment/GetToken';

                case self::URL_CHECK:

                    return $this->bankTestBaseUrl.'/pasargad/pep.shaparak.ir/Api/v1/Payment/CheckTransactionResult';

                case self::URL_VERIFY:

                    return $this->bankTestBaseUrl.'/pasargad/pep.shaparak.ir/Api/v1/Payment/VerifyPayment';

                case self::URL_REFUND:

                    return $this->bankTestBaseUrl.'/pasargad/pep.shaparak.ir/Api/v1/Payment/RefundPayment';

                case self::UPDATE_SUBPAYMENT:

                    return $this->bankTestBaseUrl.'/pasargad/pep.shaparak.ir/Api/v1/Payment/UpdateInvoiceSubPayment';

                case self::GET_SUBPAYMENT:

                    return $this->bankTestBaseUrl.'/pasargad/pep.shaparak.ir/Api/v1/Payment/GetSubPaymentsReport';

            }
        }
        throw new Exception("could not find url for {$action} action");
    }
}
