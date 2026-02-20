<?php

namespace PhpMonsters\Shaparak\Provider;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OzoneProvider extends AbstractProvider
{
    const JWT_TTL_MINUTES = 19;
    protected bool $refundSupport = true;

    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    public function getFormParameters(): array
    {
        $token = $this->requestToken();

        return [
            'gateway' => 'ozone',
            'method' => 'GET',
            'action' => $this->getUrlFor(self::URL_GATEWAY),
            'parameters' => ['pgc' => $token],
        ];
    }

    /**
     *
     * @throws Exception
     */
    protected function requestToken(): string
    {
        $transaction = $this->getTransaction();

        if ($this->getTransaction()->isReadyForTokenRequest() === false) {
            throw new RuntimeException('transaction is not ready for requesting token from payment gateway');
        }

        $this->checkRequiredActionParameters([
            'user_mobile',
        ]);

        $response = Http::acceptJson()
            ->withToken($this->getJwtToken())
            ->post($this->getUrlFor(self::URL_TOKEN), [
                'mobileNumber' => (string) $this->getParameters('user_mobile'),
                'invoiceNumber' => (string) $this->getGatewayOrderId(),
                'amount' => $this->getAmount(),
                'redirectUrl' => $this->getCallbackUrl(),
                'isVerificationNeeded' => true,
                "invoiceItems" => $this->getParameters('invoiceItems'),
            ]);

        if ($response->successful()) {
            $transaction->setGatewayToken($response->json('paymentGatewayCode')); // update transaction
            return $transaction->getGatewayToken();
        }

        $this->log($response->body());
        throw new RuntimeException('shaparak::shaparak.token_failed');
    }

    /**
     * {@inheritDoc}
     */
    public function getUrlFor(?string $action): string
    {
        $domain = $this->environment === 'production'
            ? 'ozone.ir'
            : 'stg.ozone.ir';

        $merchantBase = "https://merchant.$domain";
        $gatewayBase = "https://upg.$domain";

        return match ($action) {
            self::AUTH => "$merchantBase/api/v2/Authentication/SignIn",
            self::URL_TOKEN => "$merchantBase/api/v1/Invoices/Online",
            self::URL_GATEWAY => $gatewayBase,
            self::URL_VERIFY => "$merchantBase/api/v1/PurchaseRequests/FinalConfirmation",
            self::URL_REFUND => "$merchantBase/api/v1/TransactionRequests/Refund",
            default => throw new RuntimeException("Invalid action: $action"),
        };
    }

    /**
     * {@inheritDoc}
     */
    public function canContinueWithCallbackParameters(): bool
    {
        try {
            $this->checkRequiredActionParameters([
                'InvoiceNumber',
                'PaymentGatewayCode',
                'Result',
                'ReferenceCode',
            ]);
        } catch (\Exception) {
            return false;
        }

        return $this->getParameters('Result') === 'Paid';
    }

    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    public function getGatewayReferenceId(): string
    {
        $this->checkRequiredActionParameters([
            'ReferenceCode',
        ]);

        return $this->getParameters('ReferenceCode');
    }

    /**
     * {@inheritDoc}
     *
     * @throws RuntimeException
     */
    public function verifyTransaction(): bool
    {
        if ($this->getTransaction()->isReadyForVerify() === false) {
            throw new Exception('shaparak::shaparak.could_not_verify_transaction');
        }

        $this->checkRequiredActionParameters([
            'PaymentGatewayCode',
        ]);

        $response = Http::acceptJson()
            ->withToken($this->getJwtToken())
            ->post($this->getUrlFor(self::URL_VERIFY), [
            'PaymentGatewayCode' => $this->getTransaction()->getGatewayToken(),
        ]);

        if ($response->successful()) {
            if ((int)$response->json('referenceCode') === $this->getParameters('ReferenceCode')) {
                $this->getTransaction()->setVerified(); // save()

                return true;
            }

            throw new RuntimeException(
                $response->json("ReferenceCode mismatched! Could not verify transaction"),
            );
        }

        throw new RuntimeException('shaparak::shaparak.could_not_verify_transaction');
    }

    public function refundTransaction(): bool
    {
        if ($this->getTransaction()->isReadyForRefund() === false) {
            throw new RuntimeException('shaparak::shaparak.could_not_refund_transaction');
        }

        $this->checkRequiredActionParameters([
            'PaymentGatewayCode',
            'InvoiceNumber',
        ]);

        $response = Http::acceptJson()
            ->withToken($this->getJwtToken())
            ->post($this->getUrlFor(self::URL_REFUND), [
            'PaymentGatewayCode' => $this->getTransaction()->getGatewayToken(),
            'InvoiceNumber' => $this->getGatewayReferenceId(),
        ]);

        if ($response->successful()) {
            if ((int)$response->json('referenceCode') === $this->getParameters('ReferenceCode')) {
                $this->getTransaction()->setRefunded(); // save()

                return true;
            }

            throw new RuntimeException(
                $response->json("ReferenceCode mismatched! Could not refund transaction"),
            );
        }

        throw new RuntimeException('shaparak::shaparak.could_not_refund_transaction');
    }

    private function getJwtToken(): string
    {
        $cacheKey = sprintf('ozone_jwt_token_%s', $this->environment);

        return Cache::remember(
            $cacheKey,
            Carbon::now()->addMinutes(self::JWT_TTL_MINUTES),
            function (): string {
                $response = Http::acceptJson()
                    ->post($this->getUrlFor(self::AUTH), [
                        'ApiKey' => $this->getParameters('apikey'),
                    ])
                    ->throw()
                    ->json();

                $token = $response['accessToken'] ?? null;

                if (!$token) {
                    throw new RuntimeException('JWT token not found in response.');
                }

                return $token;
            },
        );
    }

    protected function getGatewayOrderIdFromCallBackParameters(): string
    {
        return $this->getParameters('InvoiceNumber');
    }
}
