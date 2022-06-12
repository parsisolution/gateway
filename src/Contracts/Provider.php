<?php

namespace Parsisolution\Gateway\Contracts;

use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\RequestTransaction;

interface Provider
{

    /**
     * Set the callback url. (Optional)
     * if it doesn't get called fallback to config file
     *
     * @param string $url
     * @return self
     */
    public function callbackUrl($url);

    /**
     * Authorize the transaction before send the user to the payment page.
     *
     * @param \Parsisolution\Gateway\Transactions\RequestTransaction $transaction
     * @return \Parsisolution\Gateway\Transactions\AuthorizedTransaction
     * @throws \Exception
     */
    public function authorize(RequestTransaction $transaction);

    /**
     * Redirect the user of the application to the provider's payment screen.
     *
     * @param \Parsisolution\Gateway\Transactions\AuthorizedTransaction $transaction
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Illuminate\Contracts\View\View
     */
    public function redirect(AuthorizedTransaction $transaction);

    /**
     * get instruction on how user of the application should be redirected to the provider's payment screen.
     *
     * @param \Parsisolution\Gateway\Transactions\AuthorizedTransaction $transaction
     * @return \Parsisolution\Gateway\RedirectResponse
     */
    public function redirectResponse($transaction);

    /**
     * Verify and Settle the transaction and get the settled transaction instance.
     *
     * @param \Parsisolution\Gateway\Transactions\AuthorizedTransaction $authorizedTransaction
     * @return \Parsisolution\Gateway\Transactions\SettledTransaction
     * @throws \Parsisolution\Gateway\Exceptions\InvalidRequestException
     * @throws \Parsisolution\Gateway\Exceptions\TransactionException
     * @throws \Parsisolution\Gateway\Exceptions\RetryException
     * @throws \Exception
     */
    public function settle(AuthorizedTransaction $authorizedTransaction);
}
