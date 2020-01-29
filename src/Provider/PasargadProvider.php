<?php


namespace Asanpay\Shaparak\Provider;

use Asanpay\Shaparak\Helper\Pasargad\RSAKeyType;
use Asanpay\Shaparak\Helper\Pasargad\RSAProcessor;
use Asanpay\Shaparak\Contracts\Provider as ProviderContract;

class PasargadProvider extends AbstractProvider implements ProviderContract
{
    protected $refundSupport = true;

    const URL_CHECK = 'check';

    /**
     * @inheritDoc
     */
    public function getFormParameters(): array
    {
        $this->checkRequiredParameters([
            'terminal_id',
            'merchant_id',
            'certificate_path',
        ]);

        $processor = new RSAProcessor($this->getParameters('certificate_path'), RSAKeyType::XMLFile);

        $terminalCode    = $this->getParameters('terminal_id');
        $merchantCode    = $this->getParameters('merchant_id');
        $redirectAddress = $this->getCallbackUrl();
        $invoiceNumber   = $this->getGatewayOrderId();
        $amount          = $this->getAmount();
        $timeStamp       = date("Y/m/d H:i:s");
        $invoiceDate     = date("Y/m/d H:i:s", strtotime($this->getTransaction()->created_at));
        $action          = 1003; // sell code

        $data = "#" . $merchantCode . "#" . $terminalCode . "#" . $invoiceNumber . "#" . $invoiceDate . "#" . $amount .
            "#" . $redirectAddress . "#" . $action . "#" . $timeStamp . "#";
        $data = sha1($data, true);
        $data = $processor->sign($data); // digital signature
        $sign = base64_encode($data); // base64_encode

        return [
            'gateway'    => 'pasargad',
            'method'     => 'POST',
            'action'     => $this->getUrlFor(self::URL_GATEWAY),
            'parameters' => [
                'invoiceNumber'   => $invoiceNumber,
                'invoiceDate'     => $invoiceDate,
                'amount'          => $amount,
                'terminalCode'    => $terminalCode,
                'merchantCode'    => $merchantCode,
                'timeStamp'       => $timeStamp,
                'action'          => $action,
                'sign'            => $sign,
                'redirectAddress' => $redirectAddress,
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

        $processor = new RSAProcessor($this->getParameters('certificate_path'), RSAKeyType::XMLFile);

        $terminalCode  = $this->getParameters('terminal_id');
        $merchantCode  = $this->getParameters('merchant_id');
        $invoiceNumber = $this->getParameters('iN');
        $invoiceDate   = $this->getParameters('iD');
        $amount        = $this->getAmount();
        $timeStamp     = date("Y/m/d H:i:s");

        $data = "#" . $merchantCode . "#" . $terminalCode . "#" . $invoiceNumber . "#" . $invoiceDate . "#" . $amount . "#" . $timeStamp . "#";

        $data = sha1($data, true);
        $data = $processor->sign($data); // digital signature
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

        $curl = $this->getCurlClient();

        $response = $curl->post($this->getUrlFor(self::URL_VERIFY), $parameters);

        $info = $curl->getTransferInfo();

        if ($info['http_code'] == 200) {
            $result = $this->parseXML($response, [
                'invoiceNumber' => $this->getParameters('iN'),
                'invoiceDate'   => $this->getParameters('iD'),
            ]);
        }

        if (isset($result, $result['actionResult'])) {
            if ($result['actionResult']['result'] == "True") {
                //@todo add card number to the transaction whenever Pasargad passed it on callback
                //$this->getTransaction()->setCardNumber(CARD_PAN, false); // no save()
                $this->getTransaction()->setVerified();

                return true;
            } else {
                $message = $result['actionResult']['resultMessage'] ?? 'shaparak::shaparak.verification_failed';
                throw new Exception($message);
            }
        } else {
            throw new Exception('shaparak::shaparak.could_not_verify_transaction');
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
            'merchant_id',
            'certificate_path',
            'iN',
            'iD',
            'tref',
        ]);

        $processor = new RSAProcessor($this->getParameters('certificate_path'), RSAKeyType::XMLFile);

        $terminalCode  = $this->getParameters('terminal_id');
        $merchantCode  = $this->getParameters('merchant_id');
        $invoiceNumber = $this->getParameters('iN');
        $invoiceDate   = $this->getParameters('iD');
        $amount        = $this->getAmount();
        $timeStamp     = date("Y/m/d H:i:s");
        $action        = 1004; // reverse code

        $data = "#" . $merchantCode . "#" . $terminalCode . "#" . $invoiceNumber . "#" . $invoiceDate . "#" . $amount . "#" . $action . "#" . $timeStamp . "#";

        $data = sha1($data, true);
        $data = $processor->sign($data); // digital signature
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

        $curl = $this->getCurlClient();

        $response = $curl->post($this->getUrlFor(self::URL_REFUND), $parameters);

        $info = $curl->getTransferInfo();

        if ($info['http_code'] == 200) {
            $result = $this->parseXML($response, [
                'invoiceNumber' => $this->getParameters('iN'),
                'invoiceDate'   => $this->getParameters('iD'),
            ]);
        }

        if (isset($result, $result['actionResult'])) {
            if ($result['actionResult']['result'] == "True") {
                //@todo add card number to the transaction whenever Pasargad passed it on callback
                //$this->getTransaction()->setCardNumber(CARD_PAN, false); // no save()
                $this->getTransaction()->setRefunded();

                return true;
            } else {
                $message = $result['actionResult']['resultMessage'] ?? 'shaparak::shaparak.refund_failed';
                throw new Exception($message);
            }
        } else {
            throw new Exception('shaparak::shaparak.could_not_refund_transaction');
        }
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
            'tref',
        ]);

        return $this->getParameters('tref');
    }


    /**
     * @inheritDoc
     */
    public function getUrlFor(string $action): string
    {
        if ($this->environment == 'production') {
            switch ($action) {
                case self::URL_GATEWAY:
                    {
                        return 'https://pep.shaparak.ir/gateway.aspx';
                    }
                case self::URL_CHECK:
                    {
                        return 'https://pep.shaparak.ir/CheckTransactionResult.aspx';
                    }
                case self::URL_VERIFY:
                    {
                        return 'https://pep.shaparak.ir/VerifyPayment.aspx';
                    }
                case self::URL_REFUND:
                    {
                        return 'https://pep.shaparak.ir/doRefund.aspx';
                    }
            }
        } else {
            switch ($action) {
                case self::URL_GATEWAY:
                    {
                        return 'http://banktest.ir/gateway/pasargad/gateway';
                    }
                case self::URL_CHECK:
                    {
                        return 'http://banktest.ir/gateway/pasargad/CheckTransactionResult';
                    }
                case self::URL_VERIFY:
                    {
                        return 'http://banktest.ir/gateway/pasargad/VerifyPayment';
                    }
                case self::URL_REFUND:
                    {
                        return 'http://banktest.ir/gateway/pasargad/doRefund';
                    }
            }
        }
    }

    /**
     * XML parser
     *
     * @param $data
     *
     * @return array
     */
    public static function parseXML(string $data, array $extra = [])
    {
        $ret    = [];
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
                    array_push($hash_stack, $val['tag']);
                    break;
                case 'close':
                    array_pop($hash_stack);
                    break;
                case 'complete':
                    array_push($hash_stack, $val['tag']);
                    if (!isset($val['value'])) {
                        $val['value'] = $temp[$val['tag']];
                    }

                    @eval("\$ret['" . implode($hash_stack, "']['") . "'] = '{$val['value']}';");
                    array_pop($hash_stack);
                    break;
            }
        }

        return $ret;
    }
}
