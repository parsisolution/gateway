<?php

namespace Parsisolution\Gateway\Contracts;

interface Transaction
{

    /**
     * Get the amount of transaction.
     *
     * @return \Parsisolution\Gateway\Transactions\Amount
     */
    public function getAmount();

    /**
     * Get the extra information about the transaction.
     *
     * @return array
     */
    public function getExtra();

    /**
     * Get the extra field information about the transaction.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getExtraField($key, $default = null);

    /**
     * Get transaction's attributes.
     *
     * @return array
     */
    public function getAttributes();
}
