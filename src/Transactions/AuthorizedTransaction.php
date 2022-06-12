<?php

namespace Parsisolution\Gateway\Transactions;

use Parsisolution\Gateway\Contracts\HasId;
use Parsisolution\Gateway\Traits\HasIdField;
use Parsisolution\Gateway\Traits\HasOrderIdField;

class AuthorizedTransaction extends AbstractTransaction implements HasId
{

    use HasIdField, HasOrderIdField;

    /**
     * The transaction's reference id.
     *
     * @var string
     */
    protected $reference_id;

    /**
     * The transaction's token.
     *
     * @var string
     */
    protected $token;

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
     * @return AuthorizedTransaction
     */
    public static function make(UnAuthorizedTransaction $transaction, $referenceId = null, $token = null)
    {
        $instance = new self();
        $instance->setRaw($transaction->getRaw());
        $instance['reference_id'] = $referenceId;
        $instance['token'] = $token;
        $instance->map([
            'amount'       => $transaction->getAmount(),
            'extra'        => $transaction->getExtra(),
            'reference_id' => $referenceId,
            'token'        => $token,
        ]);

        return $instance;
    }

    /**
     * Get the reference id of the transaction.
     *
     * @return string
     */
    public function getReferenceId()
    {
        return $this->reference_id;
    }

    /**
     * Get token of the transaction.
     *
     * @return string
     */
    public function getToken()
    {
        return $this->token;
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
        $instance->setRaw($transaction);
        $amount = new Amount($transaction['amount'], $transaction['currency']);
        $instance->map([
            'amount'       => $amount,
            'extra'        => json_decode($transaction['extra'], true),
            'reference_id' => $transaction['reference_id'],
            'token'        => $transaction['token'],
        ]);

        return $instance;
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
