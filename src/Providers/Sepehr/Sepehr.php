<?php

namespace Parsisolution\Gateway\Providers\Sepehr;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\ApiType;
use Parsisolution\Gateway\Curl;
use Parsisolution\Gateway\Exceptions\InvalidRequestException;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\SoapClient;
use Parsisolution\Gateway\Transactions\Amount;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\FieldsToMatch;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class Sepehr extends AbstractProvider
{
    /**
     * Address of SOAP server
     *
     * @var string
     */
    const SERVER_SOAP_URL = 'https://sepehr.shaparak.ir:8082/';

    /**
     * Address of REST server
     *
     * @var string
     */
    const SERVER_REST_URL = 'https://sepehr.shaparak.ir:8081/V1/PeymentApi/';

    /**
     * Address of gate for redirect
     *
     * @var string
     */
    const GATE_URL = 'https://sepehr.shaparak.ir:8080/';

    /**
     * Type of api to use
     *
     * @var string
     */
    protected $apiType;

    public function __construct(Container $app, $id, $config)
    {
        parent::__construct($app, $id, $config);

        $this->apiType = Arr::get($config, 'api-type', ApiType::REST);
    }

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $fields = [
            'TerminalID'  => $this->config['terminal-id'],
            'InvoiceID'   => $transaction->getOrderId(),
            'Amount'      => $transaction->getAmount()->getRiyal(),
            'callbackURL' => $this->getCallback($transaction),
            'CellNumber'  => $transaction->getExtraField('mobile'),
            'Payload'     => $transaction->getExtraField('payload'),
        ];

        $token = $this->callApi('GetToken', $fields)->AccessToken;

        $gateUrl = self::GATE_URL.$transaction->getExtraField('transaction_type', 'Pay');
        $redirectResponse = new RedirectResponse(RedirectResponse::TYPE_POST, $gateUrl, [
            'TerminalID'   => $this->config['terminal-id'],
            'token'        => $token,
            'nationalCode' => $transaction->getExtraField('national_code'),
            'getMethod'    => (Arr::get($this->config, 'get-method') ? 1 : 0),
        ]);

        return AuthorizedTransaction::make($transaction, null, $token, $redirectResponse);
    }

    /**
     * {@inheritdoc}
     */
    protected function validateSettlementRequest(Request $request)
    {
        if (! $request->has('respcode')) {
            throw new InvalidRequestException();
        }

        $code = $request->input('respcode');
        $message = $request->input('respmsg');

        if ($code != 0) {
            throw new SepehrException($code, $message);
        }

        $invoiceId = $request->input('invoiceid');
        $amount = $request->input('amount');

        return new FieldsToMatch($invoiceId, null, null, new Amount($amount, 'IRR'));
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $digitalReceipt = $request->input('digitalreceipt'); // just for "Pay" transactions
        $traceNumber = $request->input('tracenumber');
        $cardNumber = $request->input('cardnumber');
        $RRN = $request->input('rrn');

        $fields = [
            'Tid'            => $this->config['terminal-id'],
            'digitalreceipt' => $digitalReceipt,
        ];

        $amount = $this->callApi('Advice', $fields)->ReturnId;

        $toMatch = new FieldsToMatch(null, null, null, new Amount($amount, 'IRR'));

        return new SettledTransaction(
            $transaction,
            $digitalReceipt ?? $traceNumber,
            $toMatch,
            $cardNumber,
            $RRN,
            [
                'digital_receipt' => $digitalReceipt, // just for "Pay" transactions
                'trace_number'    => $traceNumber,
                'date_paid'       => $request->input('datePaid'),
                'issuer_bank'     => $request->input('issuerbank'),
                'charge'          => [
                    'reference' => $request->input('refcharge'), // for all charge types
                    'pin'       => $request->input('pincharge'), // just for card charge type
                    'serial'    => $request->input('serialcharge'), // just for card charge type
                ],
            ]
        );
    }

    /**
     * @return mixed
     *
     * @throws SepehrException
     * @throws \SoapFault
     */
    public function rollback(string $digitalReceipt)
    {
        $fields = [
            'Tid'            => $this->config['terminal-id'],
            'digitalreceipt' => $digitalReceipt,
        ];

        return $this->callApi('RollBack', $fields);
    }

    /**
     * @return mixed
     *
     * @throws SepehrException
     * @throws \SoapFault
     */
    protected function callApi(string $method, array $fields)
    {
        if ($this->apiType == ApiType::SOAP) {
            $soap = new SoapClient(
                self::SERVER_SOAP_URL.($method == 'GetToken' ? 'Token.svc?wsdl' : 'ipg.svc?wsdl'),
                $this->soapConfig()
            );

            $response = $soap->{$method}($fields);
        } else {
            $path = $this->getRestPathFromSoapMethod($method);

            [$response, $http_code] = Curl::execute(self::SERVER_REST_URL.$path, $fields, false);

            if ($http_code != 200) {
                throw new SepehrException($http_code);
            }
        }

        if (empty($response->Status) || $response->Status == 'NOK') {
            throw new SepehrException($response->ReturnId ?? -1);
        }
        if ($response->Status != 0 && $response->Status != 'OK' && $response->Status != 'Duplicate') {
            throw new SepehrException($response->Status);
        }

        return $response;
    }

    /**
     * @param  string  $method <p>
     * the soap method to call
     * </p>
     * @return string the equivalent path for rest api
     */
    protected function getRestPathFromSoapMethod(string $method)
    {
        $map = [
            'GetToken' => 'GetToken',
            'Advice'   => 'Advice',
            'RollBack' => 'Rollback',
        ];

        return $map[$method] ?? $method;
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedExtraFieldsSample()
    {
        return [
            'mobile'        => '09124441122',
            'national_code' => 'یک پارامتر اختیاری میباشد که موارد استفاده آن به این شرح میباشد:'.
                ' ۱. بررسی تطابق شماره کارت و کد ملی:'.
                ' در صورت ارسال این فیلد باید شماره کارت وارد شده در صفحه پرداخت حتما متعلق به همان کد ملی باشد.'.
                ' (استفاده از این قابلیت، با درخواست مکتوب برای پذیرندگان امکان پذیر خواهد شد)'.
                ' ۲. خرید شناسه دار: این فیلد در اینجا همان شناسه پرداخت میباشد.'.
                ' (استفاده از این قابلیت، بدون نیاز به درخواست کتبی سرویس برای شما فعال میباشد)'.
                ' چنانچه هردو قابلیت پرداخت شناسه دار و تطابق شماره کارت و کد ملی مورد نیاز پذیرنده باشد'.
                ' با درخواست کتبی پذیرنده استفاده از هر دو قابلیت امکان پذیر میگردد',
            'payload' => 'برای تسهیم با مقدار متغیر یا پرداخت قبض و یا خرید شارژ و بسته‌های اینترنتی'.
                ' میتوان از این فیلد استفاده کرد برای اطلاعات بیشتر مستندات درگاه مطالعه شود',
            'transaction_type' => 'Pay (default) (پرداخت) || '.
                'Bill (پرداخت قبض) || '.
                'BatchBill (پرداخت قبض گروهی) || '.
                'Charge (شارژ) || '.
                'Mpay (پرداخت با شماره موبایل) || '.
                'MBill (پرداخت قبض با شماره موبایل) || '.
                'MBatchBill (پرداخت قبض گروهی با شماره موبایل) || '.
                'MCharge (شارژ با شماره موبایل)',
        ];
    }
}
