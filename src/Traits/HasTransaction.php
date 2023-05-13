<?php

namespace Parsisolution\Gateway\Traits;

trait HasTransaction
{
    /**
     * @var mixed
     */
    protected $transaction;

    /**
     * Get the transaction
     *
     * @return mixed
     */
    public function getTransaction()
    {
        return $this->transaction;
    }

    /**
     * Set the transaction
     *
     * @param  mixed  $transaction
     */
    public function setTransaction($transaction): void
    {
        $this->transaction = $transaction;
    }
}
