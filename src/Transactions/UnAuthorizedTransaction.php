<?php

namespace Parsisolution\Gateway\Transactions;

use Parsisolution\Gateway\Contracts\HasId;
use Parsisolution\Gateway\Traits\HasIdField;
use Parsisolution\Gateway\Traits\HasOrderIdField;

class UnAuthorizedTransaction extends AbstractTransaction implements HasId
{
    use HasIdField, HasOrderIdField;

    /**
     * UnAuthorizedTransaction constructor.
     *
     * @param RequestTransaction $transaction
     * @param string $id
     * @param string $orderId
     */
    public function __construct(RequestTransaction $transaction, $id, $orderId)
    {
        $this->setAttributes($transaction->getAttributes());
        $this['id'] = $id;
        $this['order_id'] = $orderId;
    }
}
