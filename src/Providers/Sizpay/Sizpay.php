<?php

namespace Parsisolution\Gateway\Providers\Sizpay;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Contracts\Provider as ProviderInterface;
use Parsisolution\Gateway\Exceptions\InvalidRequestException;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\SoapClient;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class Sizpay extends AbstractProvider implements ProviderInterface
{

    /**
     * Address of SOAP server
     *
     * @var string
     */
    const SERVER_URL = 'https://rt.sizpay.com/KimiaIPGRouteService.asmx?WSDL';

    /**
     * Address of gate for redirect
     *
     * @var string
     */
    const GATE_URL = 'https://rt.sizpay.com/Route/Payment';

    /**
     * Get this provider name to save on transaction table.
     * and later use that to verify and settle
     * callback request (from transaction)
     *
     * @return string
     */
    protected function getProviderName()
    {
        return GatewayManager::SIZPAY;
    }

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $fields = array(
            'MerchantID'  => Arr::get($this->config, 'merchant-id'),
            'TerminalID'  => Arr::get($this->config, 'terminal-id'),
            'Amount'      => $transaction->getAmount()->getRiyal(),
            'DocDate'     => '',
            'OrderID'     => $transaction->getId(),
            'ReturnURL'   => $this->getCallback($transaction),
            'ExtraInf'    => json_encode($transaction->getExtra()),
            'InvoiceNo'   => $transaction->getId(),
            'AppExtraInf' => '',
            'SignData'    => '',
            'UserName'    => Arr::get($this->config, 'key'),
            'Password'    => Arr::get($this->config, 'iv'),
        );

        $soap = new SoapClient(self::SERVER_URL, $this->soapConfig(), ['encoding' => 'UTF-8']);
        $response = $soap->GetToken2($fields);

        $result = json_decode($response->GetToken2Result, true);
        $Token = $result["Token"];
        $ResCod = $result["ResCod"];
        $Message = $result["Message"];

        if ($ResCod == '0' || $ResCod == '00') {
            return AuthorizedTransaction::make($transaction, $Token);
        } else {
            throw new SizpayException($ResCod, $Message);
        }
    }

    /**
     * Redirect the user of the application to the provider's payment screen.
     *
     * @param AuthorizedTransaction $transaction
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Illuminate\Contracts\View\View
     */
    protected function redirectToGateway(AuthorizedTransaction $transaction)
    {
        $data = [
            'MerchantID' => $this->config['merchant-id'],
            'TerminalID' => $this->config['terminal-id'],
            'UserName'   => $this->config['key'],
            'Password'   => $this->config['iv'],
            'Token'      => $transaction->getReferenceId(),
        ];

        return $this->view('gateway::sizpay-redirector')->with($data);
    }

    /**
     * @inheritdoc
     */
    protected function validateSettlementRequest(Request $request)
    {
        $ResCod = $request->input('ResCod');

        if ($ResCod == '0' || $ResCod == '00') {
            return true;
        }

        throw new InvalidRequestException();
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $fields = [
            'MerchantID' => Arr::get($this->config, 'merchant-id'),
            'TerminalID' => Arr::get($this->config, 'terminal-id'),
            'Token'      => $transaction->getReferenceId(),
            'SignData'   => '',
            'UserName'   => Arr::get($this->config, 'key'),
            'Password'   => Arr::get($this->config, 'iv'),
        ];

        $soap = new SoapClient(self::SERVER_URL, $this->soapConfig(), ['encoding' => 'UTF-8']);
        $response = $soap->Confirm2($fields);

        $result = json_decode($response->Confirm2Result, true);
        $CardNo = $result["CardNo"];
        $TraceNo = $result["TraceNo"];
//        $TransDate = $result["TransDate"];
//        $TransNo = $result["TransNo"];
//        $MerchantID = $result["MerchantID"];
//        $TerminalID = $result["TerminalID"];
//        $OrderID = $result["OrderID"];
//        $RefNo = $result["RefNo"];
//        $Amount = $result["Amount"];
//        $InvoiceNo = $result["InvoiceNo"];
//        $ExtraInf = $result["ExtraInf"];
//        $AppExtraInf = $result["AppExtraInf"];
//        $Token = $result["Token"];
        $ResCod = $result["ResCod"];
        $Message = $result["Message"];

        if ($ResCod == '0' || $ResCod == '00') {
            return new SettledTransaction($transaction, $TraceNo, $CardNo);
        }

        throw new SizpayException($ResCod, $Message);
    }
}
