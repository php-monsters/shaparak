<?php

namespace PhpMonsters\Shaparak\Provider;

use Illuminate\Support\Facades\Http;

class SaderatProvider extends AbstractProvider
{
    protected bool $refundSupport = true;

    /**
     * {@inheritDoc}
     */
    public function getFormParameters(): array
    {
        return [
            'gateway' => 'saderat',
            'method' => 'post',
            'action' => $this->getUrlFor(self::URL_GATEWAY),
            'parameters' => [
                'token' => $this->requestToken(),
                'TerminalID' => $this->getParameters('terminal_id'),
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getUrlFor(string $action = null): string
    {
        if ($this->environment == 'production') {
            switch ($action) {
                case self::URL_GATEWAY:

                    return 'https://sepehr.shaparak.ir:8080/Pay';

                case self::URL_TOKEN:

                    return 'https://sepehr.shaparak.ir:8081/V1/PeymentApi/GetToken';

                case self::URL_VERIFY:

                    return 'https://sepehr.shaparak.ir:8081/V1/PeymentApi/Advice';

                case self::URL_REFUND:

                    return 'https://sepehr.shaparak.ir:8081/V1/PeymentApi/Rollback';

            }
        } else {
            switch ($action) {
                case self::URL_GATEWAY:

                    return $this->bankTestBaseUrl.'/saderat/sepehr.shaparak.ir/Pay';

                case self::URL_TOKEN:

                    return $this->bankTestBaseUrl.'/saderat/sepehr.shaparak.ir/V1/PeymentApi/GetToken';

                case self::URL_VERIFY:

                    return $this->bankTestBaseUrl.'/saderat/sepehr.shaparak.ir/V1/PeymentApi/Advice';

                case self::URL_REFUND:

                    return $this->bankTestBaseUrl.'/saderat/sepehr.shaparak.ir/V1/PeymentApi/Rollback';

            }
        }
        throw new Exception("could not find url for {$action} action");
    }

    /**
     * {@inheritDoc}
     *
     * @throws Exception
     * @throws \JsonException
     */
    protected function requestToken(): string
    {
        $this->log(__METHOD__);

        if ($this->getTransaction()->isReadyForTokenRequest() === false) {
            throw new Exception('transaction is not ready for requesting token from payment gateway');
        }

        $this->checkRequiredActionParameters([
            'terminal_id',
        ]);

        $response = Http::acceptJson()
            ->throw()
            ->post($this->getUrlFor(self::URL_TOKEN), [
                'Amount' => $this->getAmount(),
                'callbackUrl' => $this->getCallbackUrl(),
                'invoiceID' => $this->getGatewayOrderId(),
                'terminalID' => $this->getParameters('terminal_id'),
            ]);

        if ($response->json('Status') === 0) {
            // got string token
            $this->getTransaction()->setGatewayToken(
                $response['Accesstoken'],
                true
            ); // update transaction reference id

            return $response->json('Accesstoken');
        }

        throw new Exception('shaparak::shaparak.token_failed');
    }

    /**
     * {@inheritDoc}
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

        $response = Http::acceptJson()
            ->throw()
            ->post($this->getUrlFor(self::URL_VERIFY), [
                'digitalreceipt' => $this->getParameters('DigitalReceipt'),
                'Tid' => $this->getParameters('terminal_id'),
            ]);

        if (in_array($response->json('Status'), ['OK', 'Duplicate'])) {
            if ((int) $response->json('ReturnId') === $this->transaction->getPayableAmount()) {
                return $this->getTransaction()->setVerified();
            } else {
                throw new Exception('shaparak::shaparak.amounts_not_match');
            }
        }

        throw new Exception('shaparak::shaparak.verify_failed');
    }

    /**
     * {@inheritDoc}
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

        $response = Http::acceptJson()
            ->throw()
            ->post($this->getUrlFor(self::URL_REFUND), [
                'digitalreceipt' => $this->getParameters('DigitalReceipt'),
                'Tid' => $this->getParameters('terminal_id'),
            ]);

        if (in_array($response->json('Status'), ['OK', 'Duplicate'])) {
            if ((int) $response->json('ReturnId') === $this->transaction->getPayableAmount()) {
                return $this->getTransaction()->setRefunded();
            } else {
                throw new Exception('shaparak::shaparak.amounts_not_match');
            }
        }

        throw new Exception('shaparak::shaparak.refund_failed');
    }

    /**
     * {@inheritDoc}
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

        $respCode = $this->getParameters('RespCode');

        return is_numeric($respCode) && (int) $respCode === 0;
    }

    /**
     * {@inheritDoc}
     */
    public function getGatewayReferenceId(): string
    {
        $this->checkRequiredActionParameters([
            'RRN',
        ]);

        return $this->getParameters('RRN');
    }
}
