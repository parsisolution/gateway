<?php

namespace Parsisolution\Gateway\Transactions;

use Parsisolution\Gateway\Contracts\HasId;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\Traits\HasIdField;
use Parsisolution\Gateway\Traits\HasOrderIdField;

class AuthorizedTransaction extends AbstractTransaction implements HasId
{
    use HasIdField, HasOrderIdField;

    /**
     * AuthorizedTransaction constructor.
     */
    private function __construct()
    {
    }

    /**
     * create new AuthorizedTransaction.
     *
     * @param UnAuthorizedTransaction $transaction
     * @param string $referenceId
     * @param string $token
     * @param RedirectResponse $redirect
     * @return self
     */
    public static function make(
        UnAuthorizedTransaction $transaction,
        $referenceId = null,
        $token = null,
        $redirect = null
    ) {
        $instance = new self();
        $instance->setAttributes($transaction->getAttributes());
        $instance['reference_id'] = $referenceId;
        $instance['token'] = $token;
        $instance['redirect'] = $redirect;

        return $instance;
    }

    /**
     * Create new instance from DB transaction table
     *
     * @param array $transaction
     * @return self
     */
    public static function makeFromDB($transaction)
    {
        $instance = new self();
        $instance->setAttributes($transaction);
        $instance['amount'] = new Amount($transaction['amount'], $transaction['currency']);
        $instance['extra'] = json_decode($transaction['extra'], JSON_OBJECT_AS_ARRAY);

        return $instance;
    }

    /**
     * Get the reference id of the transaction.
     *
     * @return string
     */
    public function getReferenceId()
    {
        return $this['reference_id'];
    }

    /**
     * Get token of the transaction.
     *
     * @return string
     */
    public function getToken()
    {
        return $this['token'];
    }

    /**
     * Get redirect response of the transaction.
     *
     * @return RedirectResponse
     */
    public function getRedirect()
    {
        return $this['redirect'];
    }

    /**
     * generate equivalent UnAuthorizedTransaction from this instance
     *
     * @return UnAuthorizedTransaction
     */
    public function generateUnAuthorized()
    {
        $transaction = new RequestTransaction($this->getAmount());
        $transaction->setExtra($this->getExtra());
        $unAuthorizedTransaction = new UnAuthorizedTransaction($transaction, $this->getId(), $this->getOrderId());

        return $unAuthorizedTransaction;
    }
}
