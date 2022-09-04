<?php

namespace Parsisolution\Gateway\Providers\NextPay;

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

class NextPay extends AbstractProvider
{

    const SERVER_SOAP = "https://api.nextpay.org/gateway/token.wsdl";
    const SERVER_REST = "https://nextpay.org/nx/gateway/token";
    const URL_PAYMENT = "https://nextpay.org/nx/gateway/payment/";
    const SERVER_VERIFY_SOAP = "https://api.nextpay.org/gateway/verify.wsdl";
    const SERVER_VERIFY_REST = "https://nextpay.org/nx/gateway/verify";

    /**
     * Type of api to use
     *
     * @var string
     */
    protected $apiType;

    public function __construct(Container $app, array $config)
    {
        parent::__construct($app, $config);

        $this->apiType = Arr::get($config, 'api-type', ApiType::REST);
    }

    /**
     * {@inheritdoc}
     */
    protected function getProviderId()
    {
        return GatewayManager::NEXTPAY;
    }

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $fields = [
            'api_key'            => $this->config['api-key'],
            'order_id'           => $transaction->getOrderId(),
            'amount'             => $transaction->getAmount(),
            'callback_uri'       => $this->getCallback($transaction),
            'customer_phone'     => $transaction->getExtraField('mobile'),
            'custom_json_fields' => $transaction->getExtraField('custom_json_fields'),
            'payer_name'         => $transaction->getExtraField('name'),
            'payer_desc'         => $transaction->getExtraField('description'),
            'auto_verify'        => ($transaction->getExtraField('auto_verify') ? 'yes' : 'no'),
            'allowed_card'       => $transaction->getExtraField('allowed_card'),
        ];

        switch ($this->apiType) {
            case ApiType::REST:
                list($response) = Curl::execute(self::SERVER_REST, $fields, false, [
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);

                return $this->verifyAuthorizationResponse($transaction, $response);
                break;
            case ApiType::SOAP:
            default:
                $soap_client = new SoapClient(
                    self::SERVER_SOAP,
                    $this->soapConfig(),
                    Arr::get($this->config, 'settings.soap.options', [])
                );
                $response = $soap_client->TokenGenerator($fields);
                $response = $response->TokenGeneratorResult;

                return $this->verifyAuthorizationResponse($transaction, $response);
                break;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function validateSettlementRequest(Request $request)
    {
        $order_id = $request->input('order_id');
        $trans_id = $request->input('trans_id');

        if (empty($order_id) || empty($trans_id)) {
            throw new InvalidRequestException();
        }

        $amount = $request->input('amount');

        return new FieldsToMatch($order_id, null, $trans_id, new Amount($amount));
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $card_holder = $request->input('card_holder');

        $fields = [
            'api_key'  => $this->config['api-key'],
            'order_id' => $transaction->getOrderId(),
            'amount'   => $transaction->getAmount()->getToman(),
            'trans_id' => $transaction->getToken(),
        ];

        switch ($this->apiType) {
            case ApiType::REST:
                list($response) = Curl::execute(self::SERVER_VERIFY_REST, $fields, false, [
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);

                return $this->verifyVerificationResponse($transaction, $response, $card_holder);
                break;
            case ApiType::SOAP:
            default:
                $soap_client = new SoapClient(
                    self::SERVER_VERIFY_SOAP,
                    $this->soapConfig(),
                    Arr::get($this->config, 'settings.soap.options', [])
                );
                $response = $soap_client->PaymentVerification($fields);
                $response = $response->PaymentVerificationResult;

                return $this->verifyVerificationResponse($transaction, $response, $card_holder);
                break;
        }
    }

    /**
     * @param UnAuthorizedTransaction $transaction
     * @param $response
     * @return AuthorizedTransaction
     * @throws NextPayException
     */
    protected function verifyAuthorizationResponse(UnAuthorizedTransaction $transaction, $response)
    {
        if (! empty($response) && is_object($response)) {
            $code = intval($response->code);
            if ($code == -1) {
                $redirectResponse = new RedirectResponse(
                    RedirectResponse::TYPE_GET,
                    self::URL_PAYMENT.$response->trans_id
                );

                return AuthorizedTransaction::make($transaction, null, $response->trans_id, $redirectResponse);
            } else {
                throw new NextPayException($code);
            }
        } else {
            throw new \RuntimeException();
        }
    }

    /**
     * @param AuthorizedTransaction $transaction
     * @param $response
     * @param $card_holder
     * @return SettledTransaction
     * @throws NextPayException
     */
    protected function verifyVerificationResponse(AuthorizedTransaction $transaction, $response, $card_holder)
    {
        if (empty($response) || ! is_object($response)) {
            throw new \RuntimeException();
        }

        $code = intval($response->code);
        if ($code != 0) {
            throw new NextPayException($code);
        }

        $toMatch = new FieldsToMatch();

        return new SettledTransaction(
            $transaction,
            $transaction->getToken(),
            $toMatch,
            $response->card_holder ?? $card_holder ?? '',
            $response->Shaparak_Ref_Id ?? '',
            [
                'transaction_date' => $response->created_at ?? '',
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedExtraFieldsSample()
    {
        return [
            'mobile'             => '09124441122',
            'custom_json_fields' => '(json_string) اطلاعات دلخواه',
            'name'               => 'نام پرداخت کننده',
            'description'        => 'توضیحات دلخواه',
            'auto_verify'        => '(bool) true || false تایید خودکار بدون نیاز به فراخوانی وریفای',
            'allowed_card'       => 'شماره کارت مجاز -'.
                ' اگر پارامتر با مقدار 16 رقمی کارت خاصی مقدار دهی شود،'.
                ' اگر تراکنش با شماره کارتی غیر از شماره کارتی که شما اعلام میکنید انجام شود، برگشت میخورد. بنابراین'.
                ' اگر میخواهید تراکنش با هر شماره کارتی پذیرفته شود، این پارامتر را خالی بگذارید یا مقدار دهی نکنید',
        ];
    }
}
