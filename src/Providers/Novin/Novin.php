<?php

namespace Parsisolution\Gateway\Providers\Novin;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\ApiType;
use Parsisolution\Gateway\Curl;
use Parsisolution\Gateway\Exceptions\InvalidRequestException;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\SoapClient;
use Parsisolution\Gateway\Transactions\Amount;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\FieldsToMatch;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class Novin extends AbstractProvider
{

    /**
     * Address of SOAP server
     *
     * @var string
     */
    const SERVER_SOAP_URL = 'https://pna.shaparak.ir/ref-payment2/jax/merchantService?wsdl';

    /**
     * Address of REST server
     *
     * @var string
     */
    const SERVER_REST_URL = 'https://pna.shaparak.ir/ref-payment2/RestServices/mts/';

    /**
     * Address of gate for redirect
     *
     * @var string
     */
    const GATE_URL = 'https://pna.shaparak.ir/_ipgw_/payment/';

    /**
     * Type of api to use
     *
     * @var string
     */
    protected $apiType;

    /**
     * session id is the response of gateway's login api which can be used in other api calls instead of user/pass
     *
     * @var string
     */
    protected $sessionId;

    public function __construct(Container $app, array $config)
    {
        parent::__construct($app, $config);

        $this->apiType = Arr::get($config, 'api-type', ApiType::SOAP);
    }

    /**
     * {@inheritdoc}
     */
    protected function getProviderId()
    {
        return GatewayManager::NOVIN;
    }

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $fields = [
            'WSContext'                => $this->generateWSContext(),
            'TransType'                => $transaction->getExtraField('trans_type', 'EN_GOODS'),
            'ReserveNum'               => $transaction->getOrderId(),
            'MerchantId'               => Arr::get($this->config, 'merchant-id', null),
            'TerminalId'               => Arr::get($this->config, 'terminal-id', null),
            'Amount'                   => $transaction->getAmount()->getRiyal(),
            'RedirectUrl'              => $this->getCallback($transaction),
            'ProductId'                => $transaction->getExtraField('product_id'),
            'GoodsReferenceID'         => $transaction->getExtraField('goods_reference_id'),
            'MerchatGoodReferenceID'   => $transaction->getExtraField('merchant_good_reference_id'),
            'MobileNo'                 => $transaction->getExtraField('mobile'),
            'Email'                    => $transaction->getExtraField('email'),
            'BillInfoList'             => $transaction->getExtraField('bill_info_list'),
            'AdditionalInfoList'       => $transaction->getExtraField('additional_info_list'),
            'IsGovermentPay'           => $transaction->getExtraField('is_government_pay'),
            'ApportionmentAccountList' => $transaction->getExtraField('apportionment_account_list'),
            'ThpServiceId'             => $transaction->getExtraField('thp_service_id'),
            'ThpPayDataList'           => $transaction->getExtraField('thp_pay_data_list'),
            'UserId'                   => $transaction->getExtraField(
                'user_id',
                $transaction->getExtraField('mobile')
            ), // to save and retrieve card info
        ];

        if (Arr::get($this->config, 'no-sign-mode')) {
            $token = $this->callApi('GenerateTokenWithNoSign', $fields)->Token;
        } else {
            $response = $this->callApi('GenerateTransactionDataToSign', $fields);

            $fields = [
                'WSContext' => $this->generateWSContext(),
                'UniqueId'  => $response->UniqueId,
                'Signature' => $this->sign($response->DataToSign),
            ];

            $token = $this->callApi('GenerateSignedDataToken', $fields)->Token;
        }

        $redirectResponse = new RedirectResponse(RedirectResponse::TYPE_POST, self::GATE_URL, [
            'token'    => $token,
            'language' => $transaction->getExtraField('language', 'fa'),
        ]);

        return AuthorizedTransaction::make($transaction, null, $token, $redirectResponse);
    }

    /**
     * {@inheritdoc}
     */
    protected function validateSettlementRequest(Request $request)
    {
        if (! $request->has('State')) {
            throw new InvalidRequestException();
        }

        $state = $request->input('State');
        if ($state != 'OK') {
            throw new NovinException($state);
        }

        $reserveNumber = $request->input('ResNum');
        $token = $request->input('token');

        return new FieldsToMatch($reserveNumber, null, $token);
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $token = $request->input('token');
        $refNum = $request->input('RefNum');
        $cardMaskPan = $request->input('CardMaskPan');
        $RRN = $request->input('CustomerRefNum');
        $card_hash_pan = $request->input('CardHashPan');

        $fields = [
            'WSContext' => $this->generateWSContext(),
            'Token'     => $token,
            'RefNum'    => $refNum,
        ];

        $amount = $this->callApi('VerifyMerchantTrans', $fields)->Amount;

        $toMatch = new FieldsToMatch(null, null, null, new Amount($amount, 'IRR'));

        return new SettledTransaction($transaction, $refNum, $toMatch, $cardMaskPan, $RRN, compact('card_hash_pan'));
    }

    /**
     * @param $token
     * @return mixed
     * @throws NovinException
     * @throws \SoapFault
     */
    public function inquiryToken($token)
    {
        $fields = [
            'WSContext' => $this->generateWSContext(),
            'Token'     => $token,
        ];

        return $this->callApi('InquiryMerchantToken', $fields);
    }

    /**
     * @param $token
     * @param $refNum
     * @return mixed
     * @throws NovinException
     * @throws \SoapFault
     */
    public function reverse($token, $refNum)
    {
        $fields = [
            'WSContext' => $this->generateWSContext(),
            'Token'     => $token,
            'RefNum'    => $refNum,
        ];

        return $this->callApi('ReverseMerchantTrans', $fields);
    }

    /**
     * @param array $fields
     * @return mixed
     * @throws NovinException
     * @throws \SoapFault
     */
    public function getTransactionReport(array $fields)
    {
        $fields = $fields + [
                'WSContext' => $this->generateWSContext(),
            ];

        return $this->callApi('getTransactionReport', $fields);
    }

    /**
     * @param string $userId
     * @return mixed
     * @throws NovinException
     * @throws \SoapFault
     */
    public function getCardInfo(string $userId)
    {
        $fields = [
            'WSContext' => $this->generateWSContext(),
            'UserId'    => $userId,
        ];

        return $this->callApi('GetCardInfo', $fields);
    }

    /**
     * @param string $sessionId
     * @return Novin
     */
    public function setSessionId(string $sessionId): self
    {
        $this->sessionId = $sessionId;

        return $this;
    }

    /**
     * @return array
     * @throws NovinException
     * @throws \SoapFault
     */
    protected function generateWSContext(): array
    {
        if (empty($this->sessionId) && Arr::get($this->config, 'auto-login', true)) {
            $this->setSessionId($this->login());
        }

        return
            (empty($this->sessionId) ? [] : ['SessionId' => $this->sessionId]) +
            [
                'UserId'   => $this->config['username'],
                'Password' => $this->config['password'],
            ];
    }

    /**
     * @return string
     * @throws NovinException
     * @throws \SoapFault
     */
    public function login(): string
    {
        $fields = ['UserName' => $this->config['username'], 'Password' => $this->config['password']];

        return $this->callApi('MerchantLogin', $fields)->SessionId;
    }

    /**
     * @param string $sessionId
     * @return string
     * @throws NovinException
     * @throws \SoapFault
     */
    public function logout(string $sessionId): string
    {
        $fields = ['SessionId' => $sessionId];

        return $this->callApi('MerchantLogout', $fields)->Result;
    }

    /**
     * @param string $method
     * @param array $fields
     * @return mixed
     * @throws NovinException
     * @throws \SoapFault
     */
    protected function callApi(string $method, array $fields)
    {
        if ($this->apiType == ApiType::SOAP) {
            $soap = new SoapClient(self::SERVER_SOAP_URL, $this->soapConfig());

            $response = $soap->{$method}(['param' => $fields])->return;
        } else {
            $path = $this->getRestPathFromSoapMethod($method);

            list($response, $http_code) = Curl::execute(self::SERVER_REST_URL.$path, $fields, false);

            if ($http_code != 200) {
                throw new NovinException($http_code);
            }
        }

        if ($response->Result != 'ER_SUCCEED' && $response->Result != 'erSucceed') {
            throw new NovinException($response->Result);
        }

        return $response;
    }

    /**
     * @param string $method <p>
     * the soap method to call
     * </p>
     * @return string the equivalent path for rest api
     */
    protected function getRestPathFromSoapMethod(string $method)
    {
        $map = [
            'MerchantLogin'                 => 'merchantLogin/',
            'MerchantLogout'                => 'merchantLogout/',
            'GenerateTokenWithNoSign'       => 'generateTokenWithNoSign/',
            'GenerateTransactionDataToSign' => 'generateTransactionDataToSign/',
            'GenerateSignedDataToken'       => 'generateSignedDataToken/',
            'InquiryMerchantToken'          => 'inquiryMerchantToken/',
            'VerifyMerchantTrans'           => 'verifyMerchantTrans/',
            'ReverseMerchantTrans'          => 'reverseMerchantTrans/',
            'getTransactionReport'          => 'getTransactionReport/',
            'GetCardInfo'                   => 'getCardInfo/',
        ];

        return $map[$method] ?? $method;
    }

    /**
     * @param $data
     * @return mixed
     */
    protected function sign($data)
    {
        $tempFilesDirectory = rtrim($this->config['temp-files-dir'], '/');

        $unsignedDataFilePath = $tempFilesDirectory.'/unsigned.txt';
        $signedDataFilePath = $tempFilesDirectory.'/signed.txt';

        $unsignedFile = fopen($unsignedDataFilePath, 'w');
        fwrite($unsignedFile, $data);
        fclose($unsignedFile);

        $signedFile = fopen($signedDataFilePath, 'w');
        fwrite($signedFile, '');
        fclose($signedFile);

        openssl_pkcs7_sign(
            $unsignedDataFilePath,
            $signedDataFilePath,
            'file://'.$this->config['certificate-path'],
            ['file://'.$this->config['certificate-path'], $this->config['certificate-password']],
            [],
            PKCS7_NOSIGS
        );

        $signedData = file_get_contents($signedDataFilePath);
        $signature = explode("\n\n", $signedData, 3)[1];

        return $signature;
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedExtraFieldsSample()
    {
        return [
            'mobile'                     => '09124441122',
            'email'                      => 'test@gmail.com',
            'trans_type'                 => 'EN_GOODS (خرید) (is default) || '.
                'EN_BILL_PAY (پرداخت قبض) || '.
                'EN_VOCHER (وچر) || '.
                'EN_TOP_UP (تاپ آپ) || '.
                'EN_THP_PAY (سرویس پرداخت ویژه)',
            'product_id'                 => 'کد محصول (اختیاری)'.
                ' (برای خرید وچر این داده اجباری میباشد و مقادیر مجاز آن به فروشنده اعلام میگردد)',
            'goods_reference_id'         => 'شناسه خرید (اختیاری)',
            'merchant_good_reference_id' => 'شناسه خرید پذیرنده (اختیاری)',
            'bill_info_list'             => [
                [
                    'BillId'      => 'شناسه قبض (اجباری)',
                    'PayId'       => 'شناسه پرداخت (اجباری)',
                    'BillAmount'  => 'مبلغ قبض (اجباری)',
                    'ServiceDesc' => 'توضیحات خدمت (اختیاری)',
                ],
                [
                    'BillId'      => 'شناسه قبض (اجباری)',
                    'PayId'       => 'شناسه پرداخت (اجباری)',
                    'BillAmount'  => 'مبلغ قبض (اجباری)',
                    'ServiceDesc' => 'توضیحات خدمت (اختیاری)',
                ],
            ],
            'thp_service_id'             => '(اختیاری)',
            'additional_info_list'       => [
                [
                    'Key'   => 'کلید داده (اجباری)',
                    'Value' => 'مقدار داده (اجباری)',
                ],
                [
                    'Key'   => 'کلید داده (اجباری)',
                    'Value' => 'مقدار داده (اجباری)',
                ],
            ],
            'is_government_pay'          => '(bool) true || false تسویه آفلاین شاپرک (اختیاری)'.
                'اگر فعال باشد فایل تسویه به صورت آفلاین به شاپرک ارسال میشود. در این صورت شناسه تسهیم اجباری است',
            'apportionment_account_list' => [
                [
                    'AccountIBAN'              => 'کد شبا (اجباری)',
                    'Amount'                   => 'مبلغ (اجباری)',
                    'ApportionmentAccountType' => 'enMain (اصلی) || enOther (سایر)'.
                        ' (نوع تسهیم) (اجباری)',
                    'SettelmentPayID'          => 'شناسه تسهیم (اختیاری)',
                ],
                [
                    'AccountIBAN'              => 'کد شبا (اجباری)',
                    'Amount'                   => 'مبلغ (اجباری)',
                    'ApportionmentAccountType' => 'enMain (اصلی) || enOther (سایر)'.
                        ' (نوع تسهیم) (اجباری)',
                    'SettelmentPayID'          => 'شناسه تسهیم (اختیاری)',
                ],
            ],
            'thp_pay_data_list'          => [
                [
                    'ItemId'    => '',
                    'ItemValue' => '',
                ],
                [
                    'ItemId'    => '',
                    'ItemValue' => '',
                ],
            ],
            'user_id'                    => 'برای امکان ذخیره‌ی شماره کارت مشتریان است،'.
                ' که میتواند شماره موبایل مشتری و یا هر کد یکتای دیگری باشد'.
                ' و برای ذخیره‌ی اطلاعات کارت (شماره کارت و تاریخ انقضا)‌ در درگاه ارسال می‌شود،'.
                ' قابل ذکر است در هر تراکنش شماره کارت با آن user_id ارسال شده در درگاه نمایش داده می‌شود'.
                ' (در صورتی که وجود نداشته باشد به صورت پیش‌فرض از مقدار mobile برای آن استفاده میشود)',
            'language'                   => 'fa || en',
        ];
    }
}
