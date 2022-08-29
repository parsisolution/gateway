<?php

namespace Parsisolution\Gateway\Providers\NextPay;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\ApiType;
use Parsisolution\Gateway\Curl;
use Parsisolution\Gateway\Exceptions\InvalidRequestException;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\SoapClient;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\FieldsToMatch;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class NextPay extends AbstractProvider
{

    const SERVER_SOAP = "https://api.nextpay.org/gateway/token.wsdl";
    const SERVER_HTTP = "https://api.nextpay.org/gateway/token.http";
    const URL_PAYMENT = "https://api.nextpay.org/gateway/payment/";
    const SERVER_VERIFY_SOAP = "https://api.nextpay.org/gateway/verify.wsdl";
    const SERVER_VERIFY_HTTP = "https://api.nextpay.org/gateway/verify.http";

    protected $api_type = ApiType::SOAP_CLIENT;

    /**
     * @param int $api_type
     * from ApiType class
     */
    public function setApiType($api_type)
    {
        $this->api_type = $api_type;
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
            'api_key'      => $this->config['api'],
            'order_id'     => $transaction->getOrderId(),
            'amount'       => $transaction->getAmount()->getToman(),
            'callback_uri' => $this->getCallback($transaction),
        ];

        switch ($this->api_type) {
            case ApiType::HTTP:
                list($response) = Curl::execute(self::SERVER_HTTP, $fields, false, [
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);

                return $this->verifyAuthorizationResponse($transaction, $response);
                break;
            case ApiType::SOAP_CLIENT:
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

        $order_id = $request->input('order_id');
        $trans_id = $request->input('trans_id');

        return new FieldsToMatch($order_id, null, $trans_id);
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $card_holder = $request->input('card_holder');

        $fields = [
            'api_key'  => $this->config['api'],
            'order_id' => $transaction->getOrderId(),
            'amount'   => $transaction->getAmount()->getToman(),
            'trans_id' => $transaction->getToken(),
        ];

        switch ($this->api_type) {
            case ApiType::HTTP:
                list($response) = Curl::execute(self::SERVER_VERIFY_HTTP, $fields, false, [
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);

                return $this->verifyVerificationResponse($transaction, $response, $card_holder);
                break;
            case ApiType::SOAP_CLIENT:
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
            $card_holder ?? ''
        );
    }
}
