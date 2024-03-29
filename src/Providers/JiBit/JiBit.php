<?php

namespace Parsisolution\Gateway\Providers\JiBit;

use Exception;
use Illuminate\Http\Request;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Curl;
use Parsisolution\Gateway\Exceptions\TransactionException;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\FieldsToMatch;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class JiBit extends AbstractProvider
{
    /**
     * Address of main CURL server
     *
     * @var string
     */
    const SERVER_URL = 'https://pg.jibit.mobi';

    const URL_PATH_AUTHENTICATE = '/authenticate';

    const URL_PATH_INITIATE = '/order/initiate';

    const URL_PATH_VERIFY = '/order/verify/';

    const URL_PATH_INQUIRY = '/order/inquiry/';

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $fields = [
            'amount'          => $transaction->getAmount()->getRiyal(),
            'callBackUrl'     => $this->getCallback($transaction),
            'userIdentity'    => $transaction->getExtraField('mobile'),
            'merchantOrderId' => $transaction->getOrderId(),
            'additionalData'  => $transaction->getExtraField('additional'),
            'description'     => $transaction->getExtraField('description'),
        ];

        [$response] = Curl::execute(self::SERVER_URL.self::URL_PATH_INITIATE, $fields, true, [
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => $this->generateHeaders(),
        ]);

        if (! isset($response['errorCode']) || $response['errorCode'] !== 0) {
            throw new JiBitException($response['errorCode'] ?? 0, $response['message'] ?? null);
        }

        $redirectResponse = new RedirectResponse(RedirectResponse::TYPE_GET, $response['result']['redirectUrl']);

        return AuthorizedTransaction::make($transaction, $response['result']['orderId'], null, $redirectResponse);
    }

    /**
     * {@inheritdoc}
     */
    protected function validateSettlementRequest(Request $request)
    {
        $status = $request->input('status');

        if ($status != 'PURCHASE_BY_USER') {
            throw new JiBitException($status);
        }

        return new FieldsToMatch();
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        [$response] = Curl::execute(
            self::SERVER_URL.self::URL_PATH_VERIFY.$transaction->getReferenceId(),
            [],
            true,
            [
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER     => $this->generateHeaders(),
            ]
        );

        if (! isset($response['errorCode']) || $response['errorCode'] != 0) {
            throw new JiBitException($response['message'] ?? 0, @$response['errorCode'] ?? null);
        }

        $toMatch = new FieldsToMatch();

        return new SettledTransaction(
            $transaction,
            $response['result']['refId'],
            $toMatch,
            '',
            '',
            $response['result']
        );
    }

    /**
     * Inquiry the transaction's status and return its response
     *
     * @return array
     *
     * @throws TransactionException
     * @throws Exception
     */
    protected function inquiryTransaction(AuthorizedTransaction $transaction)
    {
        [$response] = Curl::execute(
            self::SERVER_URL.self::URL_PATH_INQUIRY.$transaction->getReferenceId(),
            [],
            true,
            [
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER     => $this->generateHeaders(),
            ]
        );

        return $response;
    }

    /**
     * @throws JiBitException
     */
    protected function generateHeaders(): array
    {
        return [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer '.$this->getToken(),
        ];
    }

    /**
     * Get a token from server
     *
     * @return string
     *
     * @throws JiBitException
     */
    protected function getToken()
    {
        $fields = [
            'username' => $this->config['api-key'],
            'password' => $this->config['api-secret'],
        ];

        [$response] = Curl::execute(self::SERVER_URL.self::URL_PATH_AUTHENTICATE, $fields, true, [
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        if (! isset($response['errorCode']) || $response['errorCode'] !== 0) {
            throw new JiBitException($response['errorCode'] ?? 0, $response['message'] ?? null);
        }

        return $response['result']['token'];
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedExtraFieldsSample()
    {
        return [
            'mobile'      => '09124441122',
            'additional'  => 'Optional additional data (optional)',
            'description' => 'Description will be show in Jibit QR payment gateway (optional)',
        ];
    }
}
