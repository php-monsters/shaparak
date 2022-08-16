<?php

namespace PhpMonsters\Shaparak\Provider;

use PhpMonsters\Shaparak\Contracts\Provider as ProviderContract;

class SaderatProvider extends AbstractProvider
{
    protected bool $refundSupport = true;

    /**
     * @inheritDoc
     * @throws \Samuraee\EasyCurl\Exception
     * @throws Exception
     * @throws \JsonException
     */
    protected function requestToken(): string
    {
        if ($this->getTransaction()->isReadyForTokenRequest() === false) {
            throw new Exception('transaction is not ready for requesting token from payment gateway');
        }

        $this->checkRequiredActionParameters([
            'terminal_id',
        ]);

        $sendParams = [
            'Amount'      => $this->getAmount(),
            'callbackUrl' => $this->getCallbackUrl(),
            'invoiceID'   => $this->getGatewayOrderId(),
            'terminalID'  => $this->getParameters('terminal_id'),
        ];

        $curl = $this->getCurl();

        $response = $curl->post($this->getUrlFor(self::URL_TOKEN), $sendParams);

        $info = $curl->getTransferInfo();

        if ((int)$info['http_code'] === 200 && !empty($response)) {
            $response = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            if (isset($response['Status']) && $response['Status'] === 0) {
                // got string token
                $this->getTransaction()->setGatewayToken(
                    $response['Accesstoken'],
                    true
                ); // update transaction reference id

                return $response['Accesstoken'];
            }

            throw new Exception(sprintf('shaparak::saderat.error_%s', $response->Status));
        }

        throw new Exception('shaparak::shaparak.token_failed');
    }

    /**
     * @inheritDoc
     */
    public function getFormParameters(): array
    {
        $token = $this->requestToken();

        return [
            'gateway'    => 'saderat',
            'method'     => 'post',
            'action'     => $this->getUrlFor(self::URL_GATEWAY),
            'parameters' => [
                'token'      => $token,
                'TerminalID' => $this->getParameters('terminal_id'),
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
            'RespCode',
            'RespMsg',
            'Amount',
            'InvoiceId',
            'TerminalId',
            'TraceNumber',
            'DatePaid',
            'DigitalReceipt',
            'IssuerBank',
            'CardNumber',
        ]);

        $sendParams = [
            'digitalreceipt' => $this->getParameters('DigitalReceipt'),
            'Tid'            => $this->getParameters('terminal_id'),
        ];

        $curl = $this->getCurl();

        $response = $curl->post($this->getUrlFor(self::URL_VERIFY), $sendParams);

        $info = $curl->getTransferInfo();

        if ($info['http_code'] == 200 && !empty($response)) {
            $response = json_decode($response);
            if ($response->Status == 'OK' || $response->Status == 'Duplicate') {
                if ($response->ReturnId == $this->transaction->getPayableAmount()) {
                    return $this->getTransaction()->setVerified();
                } else {
                    throw new Exception('shaparak::shaparak.amounts_not_match');
                }
            } else {
                throw new Exception('shaparak::saderat.error_' . strval($response->ReturnId));
            }
        } else {
            throw new Exception('shaparak::shaparak.token_failed');
        }
    }

    /**
     * @inheritDoc
     */
    public function refundTransaction(): bool
    {
        if ($this->getTransaction()->isReadyForRefund() == false) {
            throw new Exception('shaparak::shaparak.could_not_refund_transaction');
        }

        $this->checkRequiredActionParameters([
            'terminal_id',
            'RespCode',
            'RespMsg',
            'Amount',
            'InvoiceId',
            'TerminalId',
            'TraceNumber',
            'DatePaid',
            'DigitalReceipt',
            'IssuerBank',
            'CardNumber',
        ]);

        $sendParams = [
            'digitalreceipt' => $this->getParameters('DigitalReceipt'),
            'Tid'            => $this->getParameters('terminal_id'),
        ];

        $curl = $this->getCurl();

        $response = $curl->post($this->getUrlFor(self::URL_REFUND), $sendParams);

        $info = $curl->getTransferInfo();

        if ($info['http_code'] == 200 && !empty($response)) {
            $response = json_decode($response);
            if ($response->Status == 'OK' || $response->Status == 'Duplicate') {
                if ($response->ReturnId == $this->transaction->getPayableAmount()) {
                    return $this->getTransaction()->setRefunded();
                } else {
                    throw new Exception('shaparak::shaparak.amounts_not_match');
                }
            } else {
                throw new Exception('shaparak::saderat.error_' . strval($response->ReturnId));
            }
        } else {
            throw new Exception('shaparak::shaparak.token_failed');
        }
    }

    /**
     * @inheritDoc
     */
    public function canContinueWithCallbackParameters(): bool
    {
        try {
            $this->checkRequiredActionParameters([
                'RespCode',
            ]);
        } catch (\Exception $e) {
            return false;
        }

        if ($this->getParameters('RespCode') == 0) {
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
            'RRN',
        ]);

        return $this->getParameters('RRN');
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
                    return 'https://mabna.shaparak.ir:8080/Pay';
                }
                case self::URL_TOKEN :
                {
                    return 'https://mabna.shaparak.ir:8081/V1/PeymentApi/GetToken';
                }
                case self::URL_VERIFY:
                {
                    return 'https://mabna.shaparak.ir:8080/V1/PaymentApi/Advice';
                }
                case self::URL_REFUND:
                {
                    return 'https://mabna.shaparak.ir:8081/V1/PeymentApi/Rollback';
                }
            }
        } else {
            throw new Exception('Banktest mock service for Saderat gateway has not implemented yet');
        }
    }
}
