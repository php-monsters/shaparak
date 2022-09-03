<?php


namespace PhpMonsters\Shaparak\Provider;

use PhpMonsters\Shaparak\Helper\Pasargad\RSAKeyType;
use PhpMonsters\Shaparak\Helper\Pasargad\RSAProcessor;

class PasargadProvider extends AbstractProvider
{
    public const URL_CHECK = 'check';
    public const UPDATE_SUBPAYMENT = 'UpdateInvoiceSubPayment';
    public const GET_SUBPAYMENT = 'GetSubPaymentsReport';
    protected const GET_TOKEN_ACTION = 1003;
    protected bool $refundSupport = true;

    /**
     * @inheritDoc
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
                'n' => $this->requestToken()
            ],
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
            'terminal_id',
            'merchant_id',
            'certificate_path',
        ]);

        $terminalCode = $this->getParameters('terminal_id');
        $merchantCode = $this->getParameters('merchant_id');
        $redirectAddress = $this->getCallbackUrl();
        $invoiceNumber = $this->getGatewayOrderId();
        $amount = $this->getAmount();
        $timeStamp = date("Y/m/d H:i:s");
        $invoiceDate = date("Y/m/d H:i:s", strtotime($this->getTransaction()->created_at));

        $sign = $this->createTokenSignature($merchantCode, $terminalCode, $invoiceNumber, $invoiceDate, $amount, $redirectAddress, $timeStamp);

        $response = Http::retry(3, 100)
            ->acceptJson()
            ->withHeaders([
                'Sign' => $sign,
            ])
            ->post($this->getUrlFor(self::URL_TOKEN), [
                'invoicenumber' => $invoiceNumber,
                'invoiceDate' => $invoiceDate,
                'amount' => $amount,
                'timeStamp' => $timeStamp,
                'action' => self::GET_TOKEN_ACTION,
                'terminalCode' => $terminalCode,
                'merchantCode' => $merchantCode,
                'redirectAddress' => $redirectAddress,
                'subpaymentlist' => $this->getParameters('subpaymentlist')
            ]);

        if ($response->successful()) {
            if ((int)$response->json('IsSuccess') === true) {
                return $response->json('token');
            }

            $this->log($response->json('Message'), $response->json(), 'error');
            throw new Exception(
                $response->json($response->json('Message'))
            );
        }

        throw new Exception('shaparak::shaparak.token_failed');
    }

    /**
     * @param $merchantCode
     * @param $terminalCode
     * @param $invoiceNumber
     * @param string $invoiceDate
     * @param int $amount
     * @param string $redirectAddress
     * @param string $timeStamp
     * @return string
     */
    private function createTokenSignature($merchantCode, $terminalCode, $invoiceNumber, string $invoiceDate, int $amount,
                                          string $redirectAddress, string $timeStamp): string
    {
        $sign = "#" . $merchantCode . "#" . $terminalCode . "#" . $invoiceNumber . "#" . $invoiceDate . "#" . $amount .
            "#" . $redirectAddress . "#" . self::GET_TOKEN_ACTION . "#" . $timeStamp . "#";

        $data = "#" . $merchantCode . "#" . $terminalCode . "#" . $invoiceNumber . "#" . $invoiceDate . "#" . $amount . "#" . $timeStamp . "#";
        $sign = sha1($sign, true);
        $sign = $this->getProcessor()->sign($sign); // digital signature
        return base64_encode($sign); // base64_encode
    }

    /**
     * @param string $certificatePath
     *
     * @return RSAProcessor
     */
    private function getProcessor(string $certificatePath = null): RSAProcessor
    {
        $path = $certificatePath ?: $this->getParameters('certificate_path');
        return new RSAProcessor($path, RSAKeyType::XMLFile);
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
                    return 'https://pep.shaparak.ir/gateway.aspx';
                }
                case self::URL_CHECK:
                {
                    return 'https://pep.shaparak.ir/Api/v1/Payment/CheckTransactionResult';
                }
                case self::URL_VERIFY:
                {
                    return 'https://pep.shaparak.ir/Api/v1/Payment/VerifyPayment';
                }
                case self::URL_REFUND:
                {
                    return 'https://pep.shaparak.ir/Api/v1/Payment/RefundPayment';
                }
                case self::UPDATE_SUBPAYMENT:
                {
                    return 'https://pep.shaparak.ir/Api/v1/Payment/UpdateInvoiceSubPayment';
                }
                case self::GET_SUBPAYMENT:
                {
                    return 'https://pep.shaparak.ir/Api/v1/Payment/GetSubPaymentsReport';
                }
            }
        } else {
            switch ($action) {
                case self::URL_GATEWAY:
                {
                    return $this->bankTestBaseUrl . '/pasargad/pep.shaparak.ir/gateway.aspx';
                }
                case self::URL_CHECK:
                {
                    return $this->bankTestBaseUrl . '/pasargad/pep.shaparak.ir/Api/v1/Payment/CheckTransactionResult';
                }
                case self::URL_VERIFY:
                {
                    return $this->bankTestBaseUrl . '/pasargad/pep.shaparak.ir/Api/v1/Payment/VerifyPayment';
                }
                case self::URL_REFUND:
                {
                    return $this->bankTestBaseUrl . '/pasargad/pep.shaparak.ir/Api/v1/Payment/RefundPayment';
                }
                case self::UPDATE_SUBPAYMENT:
                {
                    return $this->bankTestBaseUrl . '/pasargad/pep.shaparak.ir/Api/v1/Payment/UpdateInvoiceSubPayment';
                }
                case self::GET_SUBPAYMENT:
                {
                    return $this->bankTestBaseUrl . '/pasargad/pep.shaparak.ir/Api/v1/Payment/GetSubPaymentsReport';
                }
            }
        }
        throw new Exception("could not find url for {$action} action");
    }

    /**
     * @inheritDoc
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

        if (!empty($this->getParameters('tref'))) {
            return true;
        }

        return false;
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function getGatewayReferenceId(): string
    {
        $this->checkRequiredActionParameters([
            'tref',
        ]);

        return $this->getParameters('tref');
    }

    /**
     * @inheritDoc
     * @throws Exception|\Samuraee\EasyCurl\Exception
     */
    public function verifyTransaction(): bool
    {
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
        if (!empty($this->getParameters('tref'))) {
            $this->getTransaction()->setGatewayToken($this->getParameters('tref'),
                true); // update transaction reference id
        } else {
            throw new Exception('could not verify transaction with callback tref: ' . $this->getParameters('tref'));
        }

        $terminalCode = $this->getParameters('terminal_id');
        $merchantCode = $this->getParameters('merchant_id');
        $invoiceNumber = $this->getParameters('iN');
        $invoiceDate = $this->getParameters('iD');
        $amount = $this->getAmount();
        $timeStamp = date("Y/m/d H:i:s");

        $data = "#" . $merchantCode . "#" . $terminalCode . "#" . $invoiceNumber . "#" . $invoiceDate . "#" . $amount . "#" . $timeStamp . "#";

        $data = sha1($data, true);
        $data = $this->getProcessor()->sign($data); // digital signature
        $sign = base64_encode($data); // base64_encode

        $parameters = compact(
            'terminalCode',
            'merchantCode',
            'invoiceNumber',
            'invoiceDate',
            'amount',
            'timeStamp',
            'sign'
        );

        $curl = $this->getCurl();

        $response = $curl->post($this->getUrlFor(self::URL_VERIFY), $parameters);

        $info = $curl->getTransferInfo();

        if ((int)$info['http_code'] === 200) {
            $result = self::parseXML($response, [
                'invoiceNumber' => $this->getParameters('iN'),
                'invoiceDate' => $this->getParameters('iD'),
            ]);
        }

        if (isset($result, $result['actionResult'])) {
            if ($result['actionResult']['result'] === "True") {
                //@todo add card number to the transaction whenever Pasargad passed it on callback
                //$this->getTransaction()->setCardNumber(CARD_PAN, false); // no save()
                $this->getTransaction()->setVerified();

                return true;
            }

            $message = $result['actionResult']['resultMessage'] ?? 'shaparak::shaparak.verification_failed';
            throw new Exception($message);
        }

        throw new Exception('shaparak::shaparak.could_not_verify_transaction');
    }

    /**
     * XML parser
     *
     * @param string $data
     *
     * @param array $extra
     *
     * @return array
     */
    public static function parseXML(string $data, array $extra = [])
    {
        $ret = [];
        $parser = xml_parser_create();
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
        xml_parse_into_struct($parser, $data, $values, $tags);
        xml_parser_free($parser);
        $hash_stack = [];

        $temp = $extra;

        foreach ($values as $key => $val) {
            switch ($val['type']) {
                case 'open':
                    $hash_stack[] = $val['tag'];
                    break;
                case 'close':
                    array_pop($hash_stack);
                    break;
                case 'complete':
                    $hash_stack[] = $val['tag'];
                    if (!isset($val['value'])) {
                        $val['value'] = $temp[$val['tag']];
                    }

                    @eval("\$ret['" . implode("']['", $hash_stack) . "'] = '{$val['value']}';");
                    array_pop($hash_stack);
                    break;
            }
        }

        return $ret;
    }

    /**
     * @inheritDoc
     * @throws Exception|\Samuraee\EasyCurl\Exception
     */
    public function refundTransaction(): bool
    {
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

        $terminalCode = $this->getParameters('terminal_id');
        $merchantCode = $this->getParameters('merchant_id');
        $invoiceNumber = $this->getParameters('iN');
        $invoiceDate = $this->getParameters('iD');
        $amount = $this->getAmount();
        $timeStamp = date("Y/m/d H:i:s");
        $action = 1004; // reverse code

        $data = "#" . $merchantCode . "#" . $terminalCode . "#" . $invoiceNumber . "#" . $invoiceDate . "#" . $amount . "#" . $action . "#" . $timeStamp . "#";

        $data = sha1($data, true);
        $data = $this->getProcessor()->sign($data); // digital signature
        $sign = base64_encode($data); // base64_encode

        $parameters = compact(
            'terminalCode',
            'merchantCode',
            'invoiceNumber',
            'invoiceDate',
            'amount',
            'timeStamp',
            'action',
            'sign'
        );

        $curl = $this->getCurl();

        $response = $curl->post($this->getUrlFor(self::URL_REFUND), $parameters);

        $info = $curl->getTransferInfo();

        if ((int)$info['http_code'] === 200) {
            $result = self::parseXML($response, [
                'invoiceNumber' => $this->getParameters('iN'),
                'invoiceDate' => $this->getParameters('iD'),
            ]);
        }

        if (isset($result, $result['actionResult'])) {
            if ($result['actionResult']['result'] === "True") {
                //@todo add card number to the transaction whenever Pasargad passed it on callback
                //$this->getTransaction()->setCardNumber(CARD_PAN, false); // no save()
                $this->getTransaction()->setRefunded();

                return true;
            }

            $message = $result['actionResult']['resultMessage'] ?? 'shaparak::shaparak.refund_failed';
            throw new Exception($message);
        }

        throw new Exception('shaparak::shaparak.could_not_refund_transaction');
    }
}
