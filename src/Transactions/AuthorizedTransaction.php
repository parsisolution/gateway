<?php

namespace Parsisolution\Gateway\Transactions;


class AuthorizedTransaction extends AbstractTransaction {

    /**
     * The transaction's reference id.
     *
     * @var string
     */
    protected $referenceId;

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
     * @return AuthorizedTransaction
     */
    public static function make(UnAuthorizedTransaction $transaction, $referenceId)
    {
        $instance = new self();
        $instance->setRaw($transaction->getRaw());
        $instance['referenceId'] = $referenceId;
        $instance->map([
            'amount'      => $transaction->getAmount(),
            'extra'       => $transaction->getExtra(),
            'id'          => $transaction->getId(),
            'referenceId' => $referenceId,
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
        return $this->referenceId;
    }

    /**
     * Get the unique identifier of the transaction.
     *
     * @return string
     */
    public function getId()
    {
        return $this['id'];
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
        $instance['referenceId'] = $transaction['ref_id'];
        $amount = new Amount($transaction['amount'], $transaction['currency']);
        $instance->map([
            'id'          => $transaction['id'],
            'amount'      => $amount,
            'extra'       => json_decode($transaction['extra'], true),
            'referenceId' => $transaction['ref_id'],
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
        $unAuthorizedTransaction = new UnAuthorizedTransaction($transaction, $this->getId());

        return $unAuthorizedTransaction;
    }
}