<?php

namespace Parsisolution\Gateway\Traits;

trait HasIdField
{
    /**
     * Get the unique identifier of the transaction.
     *
     * @return string
     */
    public function getId()
    {
        return $this['id'];
    }
}
