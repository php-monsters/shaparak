<?php


namespace PhpMonsters\Shaparak\Provider;

use PhpMonsters\Shaparak\Helper\Pasargad\RSAKeyType;
use PhpMonsters\Shaparak\Helper\Pasargad\RSAProcessor;

class PasargadProvider extends AbstractProvider
{
    public const URL_CHECK = 'check';
    public const UPDATE_SUBPAYMENT = 'UpdateInvoiceSubPayment';
    public const GET_SUBPAYMENT = 'GetSubPaymentsReport';
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

        $terminalCode = $this->getParameters('terminal_id');
        $merchantCode = $this->getParameters('merchant_id');
        $redirectAddress = $this->getCallbackUrl();
        $invoiceNumber = $this->getGatewayOrderId();
        $amount = $this->getAmount();
        $timeStamp = date("Y/m/d H:i:s");
        $invoiceDate = date("Y/m/d H:i:s", strtotime($this->getTransaction()->created_at));
        $action = 1003; // sell code

        $data = "#" . $merchantCode . "#" . $terminalCode . "#" . $invoiceNumber . "#" . $invoiceDate . "#" . $amount .
            "#" . $redirectAddress . "#" . $action . "#" . $timeStamp . "#";

        $data = sha1($data, true);
        $data = $this->getProcessor()->sign($data); // digital signature
        $sign = base64_encode($data); // base64_encode

        return [
            'gateway' => 'pasargad',
            'method' => 'POST',
            'action' => $this->getUrlFor(self::URL_GATEWAY),
            'parameters' => [
                'invoiceNumber' => $invoiceNumber,
                'invoiceDate' => $invoiceDate,
                'amount' => $amount,
                'terminalCode' => $terminalCode,
                'merchantCode' => $merchantCode,
                'timeStamp' => $timeStamp,
                'action' => $action,
                'sign' => $sign,
                'redirectAddress' => $redirectAddress,
            ],
        ];
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
    protected function verifyTransaction(): bool
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
    protected function refundTransaction(): bool
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
