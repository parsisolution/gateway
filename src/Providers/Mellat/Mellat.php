<?php

namespace Parsisolution\Gateway\Providers\Mellat;

use DateTime;
use Illuminate\Http\Request;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\SoapClient;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\FieldsToMatch;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class Mellat extends AbstractProvider
{

    /**
     * Address of main SOAP server
     *
     * @var string
     */
    const SERVER_URL = 'https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl';

    /**
     * Address of gate for redirect
     *
     * @var string
     */
    const GATE_URL = 'https://bpm.shaparak.ir/pgwchannel/startpay.mellat';

    /**
     * {@inheritdoc}
     */
    protected function getProviderId()
    {
        return GatewayManager::MELLAT;
    }

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $dateTime = new DateTime();

        $fields = [
            'terminalId'     => $this->config['terminal-id'],
            'userName'       => $this->config['username'],
            'userPassword'   => $this->config['password'],
            'orderId'        => $transaction->getOrderId(),
            'amount'         => $transaction->getAmount()->getRiyal(),
            'localDate'      => $dateTime->format('Ymd'),
            'localTime'      => $dateTime->format('His'),
            'additionalData' => $transaction->getExtraField('description'),
            'callBackUrl'    => $this->getCallback($transaction),
            'payerId'        => $transaction->getExtraField('payer_id', 0),
            // following fields exist in SOAP reference but are not documented in Behpardakht documentation
            // it seams corresponding fields send to gateway throw post fields in redirect
//            'mobileNo'       => '',
//            'encPan'         => '',
//            'panHiddenMode'  => '',
//            'cartItem'       => '',
//            'enc'            => '',
        ];

        $soap = new SoapClient(self::SERVER_URL, $this->soapConfig());
        $response = $soap->bpPayRequest($fields);

        $response = explode(',', $response->return);

        $resCode = $response[0];
        if ($resCode != '0') {
            throw new MellatException($resCode);
        }

        $referenceId = $response[1];

        $data = [
            'RefId' => $referenceId,
        ];

        $mobile = $transaction->getExtraField('mobile');
        if (!empty($mobile)) {
            $data['MobileNo'] = '98'.substr($mobile, 1);
        }

        $cartItem = $transaction->getExtraField('cart_item');
        if (!empty($cartItem)) {
            $data['CartItem'] = $cartItem;
        }

        $inputPan = $transaction->getExtraField('allowed_card');
        if (!empty($inputPan)) {
            $data['HiddenMode'] = ($transaction->getExtraField('hidden_mode', true) == true ? 0 : 1);
            $data['EncPan'] = $this->encrypt($inputPan);
        }

        $nationalCode = $transaction->getExtraField('national_code');
        if (!empty($nationalCode)) {
            $data['ENC'] = $this->encrypt($nationalCode, false);
        }

        $redirectResponse = new RedirectResponse(RedirectResponse::TYPE_POST, self::GATE_URL, $data);

        return AuthorizedTransaction::make($transaction, $referenceId, null, $redirectResponse);
    }

    /**
     * {@inheritdoc}
     */
    protected function validateSettlementRequest(Request $request)
    {
        $resCode = $request->input('ResCode');

        if ($resCode != '0') {
            throw new MellatException($resCode);
        }

        $refId = $request->input('RefId');
        $saleOrderId = $request->input('SaleOrderId');

        return new FieldsToMatch($saleOrderId, $refId);
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $traceNumber = $request->input('SaleReferenceId');
        $cardNumber = $request->input('CardHolderPan');
        $credit_card_sale_response_detail = $request->input('CreditCardSaleResponseDetail');
        $final_amount = $request->input('FinalAmount');

        $fields = [
            'terminalId'      => $this->config['terminal-id'],
            'userName'        => $this->config['username'],
            'userPassword'    => $this->config['password'],
            'orderId'         => $transaction->getOrderId(),
            'saleOrderId'     => $transaction->getOrderId(),
            'saleReferenceId' => $traceNumber,
        ];

        $soap = new SoapClient(self::SERVER_URL, $this->soapConfig());
        $response = $soap->bpVerifyRequest($fields);

        if ($response->return != '0') {
            throw new MellatException($response->return);
        }

        $response = $soap->bpSettleRequest($fields);

        if ($response->return != '0' && $response->return != '45') {
            throw new MellatException($response->return);
        }

        $toMatch = new FieldsToMatch();

        return new SettledTransaction($transaction, $traceNumber, $toMatch, $cardNumber, '', compact(
            'credit_card_sale_response_detail',
            'final_amount'
        ));
    }

    /**
     * Encrypts data with predefined gateway's key
     *
     * @param string $data
     * @param bool $no_padding
     * @return string
     */
    protected function encrypt(string $data, bool $no_padding = true): string
    {
        $options = OPENSSL_RAW_DATA;
        if ($no_padding) {
            $options |= OPENSSL_NO_PADDING;
        }
        $encryptedPan = bin2hex(openssl_encrypt(
            hex2bin($data),
            'DES-ECB',
            hex2bin('2C7D202B960A96AA'),
            $options
        ));

        return $encryptedPan;
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedExtraFieldsSample()
    {
        return [
            'mobile'        => '09124441122',
            'description'   => 'send to additionalData filed (maximum 1000 characters)',
            'payer_id'      => '(long) شناسه پرداخت کننده',
            'cart_item'     => 'چنانچه پذيرنده بخواهد توضيحات اضافه‌تری را در صفحه دروازه پرداخت نمايش دهد',
            'allowed_card'  => 'شماره کارت دارنده حساب مثال: 6037991020304050',
            'hidden_mode'   =>
                '(bool) true || false (اگر فعال باشد'.
                ' صرفا ۴ شماره آخر شماره کارت ارسالی در درگاه پرداخت بصورت ReadOnly نمايش داده خواهد شد'.
                ' در غیر این صورت شماره کارت ارسالی در درگاه پرداخت به صورت کامل و ReadOnly نمایش داده خواهد شد)',
            'national_code' => 'کد ملي دارنده حساب مثال: 1233445566',
        ];
    }
}
