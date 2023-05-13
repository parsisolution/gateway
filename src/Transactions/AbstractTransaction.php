<?php

namespace Parsisolution\Gateway\Transactions;

use ArrayAccess;
use Illuminate\Support\Arr;
use Parsisolution\Gateway\Contracts\Transaction;

abstract class AbstractTransaction implements ArrayAccess, Transaction
{
    /**
     * The transaction's attributes.
     *
     * @var array
     */
    private $attributes = [];

    /**
     * Check if a property is set
     *
     * @param  string  $name <p>
     * Name of the property
     * </p>
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->attributes[$name]);
    }

    /**
     * Get access to a property
     *
     * @param  string  $name <p>
     * Name of the property
     * </p>
     * @return mixed Property value
     */
    public function &__get($name)
    {
        return $this->attributes[$name];
    }

    /**
     * Overwrite a property
     *
     * @param  string  $name <p>
     * Name of the property
     * </p>
     * @param  mixed  $value <p>
     * New property value
     * </p>
     */
    public function __set($name, $value)
    {
        $this->attributes[$name] = $value;
    }

    /**
     * Remove a property
     *
     * @param  string  $name <p>
     * Name of the property to remove
     * </p>
     */
    public function __unset($name)
    {
        unset($this->attributes[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset)
    {
        return isset($this->attributes[$offset]);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset)
    {
        return isset($this->attributes[$offset]) ? $this->attributes[$offset] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $value)
    {
        if (! is_null($offset)) {
            $this->attributes[$offset] = $value;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset)
    {
        unset($this->attributes[$offset]);
    }

    /**
     * {@inheritdoc}
     */
    public function getAmount()
    {
        return $this['amount'];
    }

    /**
     * {@inheritdoc}
     */
    public function getExtra()
    {
        return is_array($this['extra']) ? $this['extra'] : [];
    }

    /**
     * {@inheritdoc}
     */
    public function getExtraField($key, $default = null)
    {
        return Arr::get($this['extra'], $key, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Set transaction's attributes.
     *
     * @return self
     */
    public function setAttributes(array $attributes)
    {
        $this->attributes = $attributes;

        return $this;
    }
}
