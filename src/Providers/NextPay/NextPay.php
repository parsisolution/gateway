<?php

namespace Parsisolution\Gateway\Providers\NextPay;

use Exception;
use Illuminate\Http\Request;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\ApiType;
use Parsisolution\Gateway\Exceptions\InvalidRequestException;
use Parsisolution\Gateway\Exceptions\TransactionException;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\SoapClient;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
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
     * Authorize payment request from provider's server and return
     * authorization response as AuthorizedTransaction
     * or throw an Error (most probably SoapFault)
     *
     * @param UnAuthorizedTransaction $transaction
     * @return AuthorizedTransaction
     * @throws Exception
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        switch ($this->api_type) {
            case ApiType::HTTP:
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, self::SERVER_HTTP);
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt(
                    $curl,
                    CURLOPT_POSTFIELDS,
                    "api_key=".$this->config['api'].
                    "&order_id=".$transaction->getOrderId().
                    "&amount=".$transaction->getAmount()->getToman().
                    "&callback_uri=".$this->getCallback($transaction)
                );
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                /** @var int | string $server_output */
                $response = json_decode(curl_exec($curl));
                curl_close($curl);

                return $this->verifyAuthorizationResponse($transaction, $response);
                break;
            case ApiType::SOAP_CLIENT:
            default:
                $soap_client = new SoapClient(self::SERVER_SOAP, $this->soapConfig());
                $response = $soap_client->TokenGenerator([
                    'api_key'      => $this->config['api'],
                    'order_id'     => $transaction->getOrderId(),
                    'amount'       => $transaction->getAmount()->getToman(),
                    'callback_uri' => $this->getCallback($transaction),
                ]);
                $response = $response->TokenGeneratorResult;

                return $this->verifyAuthorizationResponse($transaction, $response);
                break;
        }
    }

    /**
     * Redirect the user of the application to the provider's payment screen.
     *
     * @param \Parsisolution\Gateway\Transactions\AuthorizedTransaction $transaction
     * @return RedirectResponse
     */
    protected function redirectToGateway(AuthorizedTransaction $transaction)
    {
        return new RedirectResponse(RedirectResponse::TYPE_GET, self::URL_PAYMENT.$transaction->getReferenceId());
    }

    /**
     * Validate the settlement request to see if it has all necessary fields
     *
     * @param Request $request
     * @return bool
     * @throws InvalidRequestException
     */
    protected function validateSettlementRequest(Request $request)
    {
        $order_id = $request->input('order_id');
        $trans_id = $request->input('trans_id');

        if (! empty($order_id) && ! empty($trans_id)) {
            return true;
        }

        throw new InvalidRequestException();
    }

    /**
     * Verify and Settle the transaction and return
     * settlement response as SettledTransaction
     * or throw a TransactionException
     *
     * @param Request $request
     * @param AuthorizedTransaction $transaction
     * @return SettledTransaction
     * @throws TransactionException
     * @throws Exception
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $order_id = $request->input('order_id');
        $trans_id = $request->input('trans_id');
        $card_holder = $request->input('card_holder');

        switch ($this->api_type) {
            case ApiType::HTTP:
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, self::SERVER_VERIFY_HTTP);
                curl_setopt($curl, CURLOPT_POST, 1);
                curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt(
                    $curl,
                    CURLOPT_POSTFIELDS,
                    "api_key=".$this->config['api'].
                    "&order_id=".$order_id.
                    "&amount=".$transaction->getAmount()->getToman().
                    "&trans_id=".$trans_id
                );
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                /** @var int | string $server_output */
                $response = json_decode(curl_exec($curl));
                curl_close($curl);

                return $this->verifyVerificationResponse($transaction, $response, $card_holder);
                break;
            case ApiType::SOAP_CLIENT:
            default:
                $soap_client = new SoapClient(self::SERVER_VERIFY_SOAP, $this->soapConfig());
                $response = $soap_client->PaymentVerification(
                    [
                        'api_key'  => $this->config['api'],
                        "order_id" => $order_id,
                        "amount"   => $transaction->getAmount()->getToman(),
                        "trans_id" => $trans_id,
                    ]
                );
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
                return AuthorizedTransaction::make($transaction, $response->trans_id);
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
        if (! empty($response) && is_object($response)) {
            $code = intval($response->code);
            if ($code == 0) {
                return new SettledTransaction($transaction, $transaction->getReferenceId(), $card_holder ?: '');
            } else {
                throw new NextPayException($code);
            }
        } else {
            throw new \RuntimeException();
        }
    }
}
