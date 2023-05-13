<?php

namespace Parsisolution\Gateway\Contracts;

interface HasId
{
    /**
     * Get the unique identifier of the transaction.
     *
     * @return string
     */
    public function getId();
}
