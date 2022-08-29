<?php

namespace Parsisolution\Gateway\Providers\Sizpay;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Contracts\Provider as ProviderInterface;
use Parsisolution\Gateway\Exceptions\InvalidRequestException;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\SoapClient;
use Parsisolution\Gateway\Transactions\Amount;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\FieldsToMatch;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class Sizpay extends AbstractProvider implements ProviderInterface
{

    /**
     * Address of SOAP server
     *
     * @var string
     */
    const SERVER_URL = 'https://rt.sizpay.ir/KimiaIPGRouteService.asmx?WSDL';

    /**
     * Address of gate for redirect
     *
     * @var string
     */
    const GATE_URL = 'https://rt.sizpay.ir/Route/Payment';

    /**
     * {@inheritdoc}
     */
    protected function getProviderId()
    {
        return GatewayManager::SIZPAY;
    }

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $appExtraInformation = [
            "PayTyp"      => $transaction->getExtraField('pay_type', 0),
            "PayTypID"    => $transaction->getExtraField('pay_type_id', 0),
            "PayerNm"     => $transaction->getExtraField('name', ''),
            "PayerMobile" => $transaction->getExtraField('mobile', ''),
            "PayerEmail"  => $transaction->getExtraField('email', ''),
            "PayerIP"     => $this->request->getClientIp(),
            "Descr"       => $transaction->getExtraField('description', ''),
            "PayTitle"    => $transaction->getExtraField('pay_title', ''),
            "PayTitleID"  => $transaction->getExtraField('pay_title_id', 0),
            "PayerAppTyp" => $transaction->getExtraField('payer_app_type'),
            "PayerAppID"  => $transaction->getExtraField('payer_app_id'),
            "PayerAppNm"  => $transaction->getExtraField('payer_app_name'),
        ];

        $fields = [
            'MerchantID'  => Arr::get($this->config, 'merchant-id'),
            'TerminalID'  => Arr::get($this->config, 'terminal-id'),
            'Amount'      => $transaction->getAmount()->getRiyal(),
            'DocDate'     => '',
            'OrderID'     => $transaction->getOrderId(),
            'ReturnURL'   => $this->getCallback($transaction),
            'ExtraInf'    => json_encode($transaction->getExtra()),
            'InvoiceNo'   => $transaction->getOrderId(),
            'AppExtraInf' => $appExtraInformation,
            'SignData'    => '',
            'UserName'    => Arr::get($this->config, 'username'),
            'Password'    => Arr::get($this->config, 'password'),
        ];

        $soap = new SoapClient(self::SERVER_URL, $this->soapConfig(), ['encoding' => 'UTF-8']);
        $response = $soap->GetToken(['GenerateTokenData' => $fields]);

        $result = $response->GetTokenResult;
        $Token = $result->Token;
        $ResCod = $result->ResCod;
        $Message = $result->Message;

        if ($ResCod != '0' && $ResCod != '00') {
            throw new SizpayException($ResCod, $Message);
        }

        $data = [
            'MerchantID' => $this->config['merchant-id'],
            'TerminalID' => $this->config['terminal-id'],
            'UserName'   => $this->config['username'],
            'Password'   => $this->config['password'],
            'Token'      => $Token,
        ];

        $redirectResponse = new RedirectResponse(RedirectResponse::TYPE_POST, self::GATE_URL, $data);

        return AuthorizedTransaction::make($transaction, null, $Token, $redirectResponse);
    }

    /**
     * {@inheritdoc}
     */
    protected function validateSettlementRequest(Request $request)
    {
        $ResCod = $request->input('ResCod');

        if ($ResCod != '0' && $ResCod != '00') {
            throw new InvalidRequestException();
        }

        return new FieldsToMatch();
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $fields = [
            'MerchantID' => Arr::get($this->config, 'merchant-id'),
            'TerminalID' => Arr::get($this->config, 'terminal-id'),
            'Token'      => $transaction->getToken(),
            'SignData'   => '',
            'UserName'   => Arr::get($this->config, 'username'),
            'Password'   => Arr::get($this->config, 'password'),
        ];

        $soap = new SoapClient(self::SERVER_URL, $this->soapConfig(), ['encoding' => 'UTF-8']);
        $response = $soap->Confirm2($fields);

        $result = json_decode($response->Confirm2Result, JSON_OBJECT_AS_ARRAY);
        $CardNo = $result["CardNo"];
        $TraceNo = $result["TraceNo"];
//        $TransDate = $result["TransDate"];
        $TransNo = $result["TransNo"];
//        $MerchantID = $result["MerchantID"];
//        $TerminalID = $result["TerminalID"];
        $OrderID = $result["OrderID"];
        $RefNo = $result["RefNo"];
        $Amount = $result["Amount"];
//        $InvoiceNo = $result["InvoiceNo"];
//        $ExtraInf = $result["ExtraInf"];
//        $AppExtraInf = $result["AppExtraInf"];
        $Token = $result["Token"];
        $ResCod = $result["ResCod"];
        $Message = $result["Message"];

        if ($ResCod != '0' && $ResCod != '00') {
            throw new SizpayException($ResCod, $Message);
        }

        $toMatch = new FieldsToMatch($OrderID, null, $Token, new Amount($Amount, 'IRR'));

        return new SettledTransaction($transaction, $TraceNo, $toMatch, $CardNo, $RefNo, $result, $TransNo);
    }
}
