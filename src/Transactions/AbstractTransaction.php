<?php

namespace Parsisolution\Gateway\Transactions;

use ArrayAccess;
use Illuminate\Support\Arr;
use Parsisolution\Gateway\Contracts\Transaction;

abstract class AbstractTransaction implements ArrayAccess, Transaction
{

    /**
     * The Transaction's amount.
     *
     * @var Amount
     */
    protected $amount;

    /**
     * The transaction's extra data.
     *
     * @var array
     */
    protected $extra;

    /**
     * The transaction's raw attributes.
     *
     * @var array
     */
    public $transaction;

    /**
     * @inheritdoc
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @inheritdoc
     */
    public function getExtra()
    {
        return is_array($this->extra) ? $this->extra : [];
    }

    /**
     * @inheritdoc
     */
    public function getExtraField($key, $default = null)
    {
        return Arr::get($this->extra, $key, $default);
    }

    /**
     * Get the raw transaction array.
     *
     * @return array
     */
    public function getRaw()
    {
        return $this->transaction;
    }

    /**
     * Set the raw transaction array from the provider.
     *
     * @param  array $transaction
     * @return self
     */
    public function setRaw(array $transaction)
    {
        $this->transaction = $transaction;

        return $this;
    }

    /**
     * Map the given array onto the transaction's properties.
     *
     * @param  array $attributes
     * @return self
     */
    public function map(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            $this->{$key} = $value;
        }

        return $this;
    }

    /**
     * Determine if the given raw transaction attribute exists.
     *
     * @param  string $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->transaction);
    }

    /**
     * Get the given key from the raw transaction.
     *
     * @param  string $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->transaction[$offset];
    }

    /**
     * Set the given attribute on the raw transaction array.
     *
     * @param  string $offset
     * @param  mixed $value
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->transaction[$offset] = $value;
    }

    /**
     * Unset the given value from the raw transaction array.
     *
     * @param  string $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->transaction[$offset]);
    }
}
