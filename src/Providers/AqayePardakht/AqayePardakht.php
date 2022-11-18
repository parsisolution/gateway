<?php

namespace Parsisolution\Gateway\Providers\AqayePardakht;

use Illuminate\Http\Request;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Contracts\Provider as ProviderInterface;
use Parsisolution\Gateway\Curl;
use Parsisolution\Gateway\Exceptions\InvalidRequestException;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\FieldsToMatch;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class AqayePardakht extends AbstractProvider implements ProviderInterface
{

    /**
     * Address of server
     *
     * @var string
     */
    const SERVER_URL = 'https://panel.aqayepardakht.ir/api/v2/';

    /**
     * Address of gate for redirect
     *
     * @var string
     */
    const GATE_URL = 'https://panel.aqayepardakht.ir/startpay/';

    /**
     * Address of sandbox gate for redirect
     *
     * @var string
     */
    const GATE_SANDBOX_URL = 'https://panel.aqayepardakht.ir/startpay/sandbox/';


    /**
     * {@inheritdoc}
     */
    protected function getProviderId()
    {
        return GatewayManager::AQAYEPARDAKHT;
    }

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $fields = [
            'pin'         => $this->config['pin'],
            'amount'      => $transaction->getAmount()->getToman(),
            'callback'    => $this->getCallback($transaction),
            'invoice_id'  => $transaction->getOrderId(),
            'mobile'      => $transaction->getExtraField('mobile'),
            'email'       => $transaction->getExtraField('email'),
            'card_number' => $transaction->getExtraField('allowed_card'),
            'description' => $transaction->getExtraField('description'),
        ];

        $result = $this->callApi('create', $fields);

        $gateUrl = $this->config['pin'] == 'sandbox' ? self::GATE_SANDBOX_URL : self::GATE_URL;
        $redirectResponse = new RedirectResponse(RedirectResponse::TYPE_GET, $gateUrl.$result['transid']);

        return AuthorizedTransaction::make($transaction, $result['transid'], null, $redirectResponse);
    }

    /**
     * {@inheritdoc}
     */
    protected function validateSettlementRequest(Request $request)
    {
        if ($this->config['pin'] != 'sandbox') {
            if (! $request->has('status')) {
                throw new InvalidRequestException();
            }

            $status = $request->input('status');
            if ($status != 1) {
                throw new AqayePardakhtException($status);
            }
        }

        return new FieldsToMatch(null, $request->input('transid'));
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $fields = [
            'pin'     => $this->config['pin'],
            'amount'  => $transaction->getAmount()->getToman(),
            'transid' => $transaction->getReferenceId(),
        ];

        $result = $this->callApi('verify', $fields);

        if ($result['code'] != '1') {
            throw new AqayePardakhtException($result['code']);
        }

        $traceNumber = $request->input('tracking_number');
        $cardNumber = $request->input('cardnumber');
        $bank = $request->input('bank');

        return new SettledTransaction(
            $transaction,
            $traceNumber,
            new FieldsToMatch(),
            $cardNumber,
            '',
            compact('bank')
        );
    }

    /**
     * @param $path
     * @param $fields
     * @return mixed
     * @throws AqayePardakhtException
     */
    protected function callApi(string $path, array $fields)
    {
        list($response, $http_code) = Curl::execute(self::SERVER_URL.$path, $fields);

        if ($http_code != 200 || empty($response['status']) || $response['status'] != 'success') {
            throw new AqayePardakhtException($response['code'] ?? $http_code);
        }

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedExtraFieldsSample()
    {
        return [
            'mobile'       => '09124441122',
            'email'        => 'test@gmail.com',
            'description'  => 'توضیحات',
            'allowed_card' => '(اختیاری) شماره کارت مجاز به پرداخت',
        ];
    }
}
