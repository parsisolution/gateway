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
     * Verify and Settle the transaction and get the settled transaction instance.
     *
     * @param \Parsisolution\Gateway\Transactions\AuthorizedTransaction $authorizedTransaction
     * @param array $fieldsToUpdateOnSuccess
     * @return \Parsisolution\Gateway\Transactions\SettledTransaction
     * @throws \Parsisolution\Gateway\Exceptions\InvalidRequestException
     * @throws \Parsisolution\Gateway\Exceptions\TransactionException
     * @throws \Parsisolution\Gateway\Exceptions\RetryException
     * @throws \Exception
     */
    public function settle(AuthorizedTransaction $authorizedTransaction, $fieldsToUpdateOnSuccess);
}
