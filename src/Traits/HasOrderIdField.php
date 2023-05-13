<?php

namespace Parsisolution\Gateway\Traits;

trait HasOrderIdField
{
    /**
     * Get order id of the transaction.
     *
     * @return string
     */
    public function getOrderId()
    {
        return $this['order_id'];
    }
}
