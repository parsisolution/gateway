<?php

namespace Parsisolution\Gateway\Transactions;


class UnAuthorizedTransaction extends AbstractTransaction {

    /**
     * The unique identifier for the transaction.
     *
     * @var mixed
     */
    protected $id;

    /**
     * UnAuthorizedTransaction constructor.
     *
     * @param RequestTransaction $transaction
     * @param mixed $id
     */
    public function __construct(RequestTransaction $transaction, $id)
    {
        $this->setRaw($transaction->getRaw());
        $this['id'] = $id;
        $this->map([
            'amount' => $transaction->getAmount(),
            'extra'  => $transaction->getExtra(),
            'id'     => $id,
        ]);
    }

    /**
     * Get the unique identifier of the transaction.
     *
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

}