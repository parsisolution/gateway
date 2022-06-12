<?php

namespace Parsisolution\Gateway\Transactions;

use Parsisolution\Gateway\Contracts\HasId;
use Parsisolution\Gateway\Traits\HasIdField;
use Parsisolution\Gateway\Traits\HasOrderIdField;

class UnAuthorizedTransaction extends AbstractTransaction implements HasId
{

    use HasIdField, HasOrderIdField;

    /**
     * The unique identifier for the transaction.
     *
     * @var mixed
     */
    protected $id;

    /**
     * The order id for the transaction.
     *
     * @var string
     */
    protected $order_id;

    /**
     * UnAuthorizedTransaction constructor.
     *
     * @param RequestTransaction $transaction
     * @param mixed $id
     * @param string $orderId
     */
    public function __construct(RequestTransaction $transaction, $id, $orderId)
    {
        $this->setRaw($transaction->getRaw());
        $this['id'] = $id;
        $this['order_id'] = $orderId;
        $this->map([
            'amount'   => $transaction->getAmount(),
            'extra'    => $transaction->getExtra(),
            'id'       => $id,
            'order_id' => $orderId,
        ]);
    }
}
