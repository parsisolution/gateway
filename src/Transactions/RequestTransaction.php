<?php

namespace Parsisolution\Gateway\Transactions;

use Illuminate\Support\Arr;

class RequestTransaction extends AbstractTransaction
{
    /**
     * RequestTransaction constructor.
     */
    public function __construct(Amount $amount)
    {
        $this->setAmount($amount);
    }

    /**
     * Set the amount of transaction.
     *
     * @param  Amount  $amount
     * @return self
     */
    public function setAmount($amount)
    {
        $this['amount'] = $amount;

        return $this;
    }

    /**
     * Set the extra information about the transaction.
     *
     * @param  array  $extra
     * @return self
     */
    public function setExtra($extra)
    {
        $this['extra'] = $extra;

        return $this;
    }

    /**
     * Set the extra information field about the transaction.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return self
     */
    public function setExtraField($key, $value)
    {
        $extra = $this['extra'];
        Arr::set($extra, $key, $value);
        $this['extra'] = $extra;

        return $this;
    }
}
