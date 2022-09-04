<?php

namespace Parsisolution\Gateway\Providers\Pasargad;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Contracts\Provider as ProviderInterface;
use Parsisolution\Gateway\Curl;
use Parsisolution\Gateway\Exceptions\InvalidRequestException;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\Providers\Pasargad\Utilities\RSAProcessor;
use Parsisolution\Gateway\Providers\Pasargad\Utilities\XMLGenerator;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\Transactions\Amount;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\FieldsToMatch;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class Pasargad extends AbstractProvider implements ProviderInterface
{

    /**
     * Address of server
     *
     * @var string
     */
    const SERVER_URL = 'https://pep.shaparak.ir/Api/v1/Payment';

    /**
     * Address of gate for redirect
     *
     * @var string
     */
    const GATE_URL = 'https://pep.shaparak.ir/payment.aspx';


    /**
     * {@inheritdoc}
     */
    protected function getProviderId()
    {
        return GatewayManager::PASARGAD;
    }

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $fields = [
            'Action'          => 1003,
            'MerchantCode'    => $this->config['merchant-code'],
            'TerminalCode'    => $this->config['terminal-code'],
            'Amount'          => $transaction->getAmount()->getRiyal(),
            'InvoiceNumber'   => $transaction->getOrderId(),
            'InvoiceDate'     => $transaction->getOrderId(),
            'RedirectAddress' => $this->getCallback($transaction),
            'Timestamp'       => date('Y/m/d H:i:s'),
        ];
        $mobile = $transaction->getExtraField('mobile');
        if (! empty($mobile)) {
            $fields['Mobile'] = substr($mobile, 1);
        }
        $email = $transaction->getExtraField('email');
        if (! empty($email)) {
            $fields['Email'] = $email;
        }
        $merchantName = $transaction->getExtraField('merchant_name');
        if ($merchantName) {
            $fields['MerchantName'] = $merchantName;
        }
        $selectedLanguage = $transaction->getExtraField('selected_language');
        if ($selectedLanguage) {
            $fields['SelectedLanguage'] = $selectedLanguage;
        }
        $pidn = $transaction->getExtraField('pidn');
        if (! empty($pidn)) {
            $fields['PIDN'] = $pidn;
        }
        if ($transaction->getExtraField('sub_payment_mode', false)) {
            $subPayments = $transaction->getExtraField('sub_payments');
            $fields['SubPaymentList'] = base64_encode(XMLGenerator::generateSubPaymentList($subPayments));
        }
        if ($transaction->getExtraField('multi_payment_mode', false)) {
            $paymentItems = $transaction->getExtraField('payment_items');
            $fields['MultiPaymentData'] = base64_encode(XMLGenerator::generateMultiPayment($paymentItems));
        }

        $url = self::SERVER_URL.'/GetToken';
        list($result, $http_code, $http_error) = Curl::executeArgs($this->generateCurlArguments($url, $fields));

        if ($http_code != 200 || empty($result) || $result['IsSuccess'] == false) {
            throw new PasargadException($http_code, $result['Message'] ?? $http_error ?? null);
        }

        if (Arr::get($this->config, 'redirect-method', RedirectResponse::TYPE_GET) == RedirectResponse::TYPE_GET) {
            $redirectResponse = new RedirectResponse(RedirectResponse::TYPE_GET, self::GATE_URL.'?n='.$result['Token']);
        } else {
            $redirectResponse = new RedirectResponse(
                RedirectResponse::TYPE_POST,
                self::GATE_URL,
                ['Token' => $result['Token']]
            );
        }

        return AuthorizedTransaction::make($transaction, null, $result['Token'], $redirectResponse);
    }

    /**
     * {@inheritdoc}
     */
    protected function validateSettlementRequest(Request $request)
    {
        if (! $request->has('iN') || ! $request->has('iD')) {
            throw new InvalidRequestException();
        }

        $invoiceNumber = $request->input('iN');

        return new FieldsToMatch($invoiceNumber);
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
//        $invoiceDate = $request->input('iD');
        $transactionReferenceId = $request->input('tref');

        $fields = [
            'MerchantCode'           => $this->config['merchant-code'],
            'TerminalCode'           => $this->config['terminal-code'],
            'InvoiceNumber'          => $transaction->getOrderId(),
            'InvoiceDate'            => $transaction->getOrderId(),
            'TransactionReferenceID' => $transactionReferenceId,
        ];

        $url = self::SERVER_URL.'/CheckTransactionResult';
        list($result, $http_code, $http_error) = Curl::executeArgs($this->generateCurlArguments($url, $fields));

        if ($http_code != 200 || empty($result) || $result['IsSuccess'] == false) {
            throw new PasargadException($http_code, $result['Message'] ?? $http_error ?? null);
        }

        $referenceNumber = $result['ReferenceNumber'];
        $traceNumber = $result['TraceNumber'];
        $transactionDate = $result['TransactionDate'];
        $transactionReferenceId = $result['TransactionReferenceID'];
        $invoiceNumber = $result['InvoiceNumber'];
//        $invoiceDate = $result['InvoiceDate'];
        $amount = $result['Amount'];

        $fields = [
            'MerchantCode'  => $this->config['merchant-code'],
            'TerminalCode'  => $this->config['terminal-code'],
            'InvoiceNumber' => $transaction->getOrderId(),
            'InvoiceDate'   => $transaction->getOrderId(),
            'Amount'        => $transaction->getAmount()->getRiyal(),
            'TimeStamp'     => date('Y/m/d H:i:s'),
        ];

        $url = self::SERVER_URL.'/VerifyPayment';
        list($result, $http_code, $http_error) = Curl::executeArgs($this->generateCurlArguments($url, $fields));

        if ($http_code != 200 || empty($result) || $result['IsSuccess'] == false) {
            throw new PasargadException($http_code, $result['Message'] ?? $http_error ?? null);
        }

        $maskedCardNumber = $result['MaskedCardNumber'];
        $hashedCardNumber = $result['HashedCardNumber'];
        $shaparakRefNumber = $result['ShaparakRefNumber'];

        $toMatch = new FieldsToMatch($invoiceNumber, null, null, new Amount($amount, 'IRR'));

        return new SettledTransaction(
            $transaction,
            $traceNumber,
            $toMatch,
            $maskedCardNumber,
            $shaparakRefNumber,
            [
                'reference_number'   => $referenceNumber,
                'transaction_date'   => $transactionDate,
                'hashed_card_number' => $hashedCardNumber,
            ],
            $transactionReferenceId
        );
    }

    /**
     * Refunds Payment
     *
     * @param AuthorizedTransaction|SettledTransaction $transaction
     * @return array
     * @throws PasargadException
     */
    public function refund($transaction)
    {
        $fields = [
            'MerchantCode'  => $this->config['merchant-code'],
            'TerminalCode'  => $this->config['terminal-code'],
            'InvoiceNumber' => $transaction->getOrderId(),
            'InvoiceDate'   => $transaction->getOrderId(),
            'Amount'        => $transaction->getAmount()->getRiyal(),
            'TimeStamp'     => date('Y/m/d H:i:s'),
        ];

        $url = self::SERVER_URL.'/RefundPayment';
        list($result, $http_code, $http_error) = Curl::executeArgs($this->generateCurlArguments($url, $fields));

        if ($http_code != 200 || empty($result)) {
            throw new PasargadException($http_code, $http_error ?? null);
        }

        return $result;
    }

    /**
     * Update Invoice's Sub Payment
     *
     * @param string $invoiceUID
     * @param array $actions
     * @return array
     * @throws PasargadException
     */
    public function updateInvoiceSubPayment(string $invoiceUID, array $actions)
    {
        $fields = [
            'MerchantCode'      => $this->config['merchant-code'],
            'TerminalCode'      => $this->config['terminal-code'],
            'InvoiceUpdateList' => base64_encode(XMLGenerator::generateInvoiceUpdateList($invoiceUID, $actions)),
            'TimeStamp'         => date('Y/m/d H:i:s'),
        ];

        $url = self::SERVER_URL.'/UpdateInvoiceSubPayment';
        list($result, $http_code, $http_error) = Curl::executeArgs($this->generateCurlArguments($url, $fields));

        if ($http_code != 200 || empty($result)) {
            throw new PasargadException($http_code, $http_error ?? null);
        }

        return $result;
    }

    /**
     * Get Invoice's Sub Payments Report
     *
     * @param string $startDate
     * @param string $endDate
     * @return array
     * @throws PasargadException
     */
    public function getSubPaymentsReport(string $startDate, string $endDate)
    {
        $fields = [
            'MerchantCode' => $this->config['merchant-code'],
            'TerminalCode' => $this->config['terminal-code'],
            'StartDate'    => $startDate,
            'EndDate'      => $endDate,
            'TimeStamp'    => date('Y/m/d H:i:s'),
        ];

        $url = self::SERVER_URL.'/GetSubPaymentsReport';
        list($result, $http_code, $http_error) = Curl::executeArgs($this->generateCurlArguments($url, $fields));

        if ($http_code != 200 || empty($result)) {
            throw new PasargadException($http_code, $http_error ?? null);
        }

        return $result;
    }

    /**
     * @param $url
     * @param $fields
     * @return array
     */
    protected function generateCurlArguments($url, $fields): array
    {
        $options = (Arr::get($this->config, 'ssl-verification', true) ? [] : [CURLOPT_SSL_VERIFYPEER => false]) +
            [CURLOPT_HTTPHEADER => $this->generateHeaders(json_encode($fields))];

        return [
            $url,
            $fields,
            true,
            $options,
        ];
    }

    /**
     * @param string $data
     * @return string[]
     */
    protected function generateHeaders(string $data): array
    {
        return [
            'Accept: application/json',
            'Content-Type: application/json',
            'Sign: '.$this->sign($data),
        ];
    }

    /**
     * Sign data using RSA key
     *
     * @var string $data
     * @return string
     */
    protected function sign(string $data): string
    {
        $keyType = strtolower(Arr::get($this->config, 'rsa-key-type', 'file'));
        $processor = new RSAProcessor(
            $this->config['rsa-key'],
            $keyType == 'file' ? RSAProcessor::XML_FILE : RSAProcessor::XML_STRING
        );

        return base64_encode($processor->sign(sha1($data, true)));
    }
}
