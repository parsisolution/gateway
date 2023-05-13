<?php

namespace Parsisolution\Gateway\Providers\DigiPay;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Contracts\Provider as ProviderInterface;
use Parsisolution\Gateway\Exceptions\InvalidRequestException;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\Transactions\Amount;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\FieldsToMatch;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class DigiPay extends AbstractProvider implements ProviderInterface
{
    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $ticket = $this->initDP()->createTicket(
            $transaction->getAmount()->getRiyal(),
            $transaction->getOrderId(),
            $this->getCallback($transaction),
            $transaction->getExtraField('mobile')
        );

        $redirectResponse = new RedirectResponse(RedirectResponse::TYPE_GET, $ticket['url']);

        return AuthorizedTransaction::make($transaction, null, $ticket['ticket'], $redirectResponse);
    }

    /**
     * {@inheritdoc}
     */
    protected function validateSettlementRequest(Request $request)
    {
        if (! $request->has('result')) {
            throw new InvalidRequestException();
        }

        $result = $request->input('result');
        if ($result != 'SUCCESS') {
            throw new DigipayException($result);
        }

        $providerId = $request->input('providerId');
        $amount = $request->input('amount');

        return new FieldsToMatch($providerId, null, null, new Amount($amount, 'IRR'));
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $trackingCode = $request->input('trackingCode');

        $result = $this->initDP()->verifyTicket($trackingCode);

        $payment_gateway = $result['paymentGateway'];
        $toMatch = new FieldsToMatch($result['providerId'], null, null, new Amount($result['amount'], 'IRR'));

        return new SettledTransaction(
            $transaction,
            $result['trackingCode'] ?? $trackingCode,
            $toMatch,
            $result['maskedPan'] ?? '',
            $result['rrn'] ?? '',
            ['verify_result' => $result] + compact('payment_gateway')
        );
    }

    protected function initDP(): DigiPayGateway
    {
        $settings = [
            'type'          => $this->config['type'],
            'username'      => $this->config['username'],
            'password'      => $this->config['password'],
            'client_id'     => $this->config['client-id'],
            'client_secret' => $this->config['client-secret'],
            'access_token'  => Cache::get('dp_access_token'),
            'refresh_token' => Cache::get('dp_refresh_token'),
            'live_api'      => ! $this->config['sandbox'],
        ];

        return new DigiPayGateway($settings, function ($accessToken, $refreshToken) {
            Cache::forever('dp_access_token', $accessToken);
            Cache::forever('dp_refresh_token', $refreshToken);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedExtraFieldsSample()
    {
        return [
            'mobile' => '09124441122',
        ];
    }
}
