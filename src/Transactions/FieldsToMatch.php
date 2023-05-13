<?php

namespace Parsisolution\Gateway\Transactions;

use Parsisolution\Gateway\Contracts\Transaction;

class FieldsToMatch
{
    /**
     * The order id for the transaction.
     *
     * @var string
     */
    protected $order_id;

    /**
     * The transaction's reference id.
     *
     * @var string
     */
    protected $reference_id;

    /**
     * The transaction's token.
     *
     * @var string
     */
    protected $token;

    /**
     * The Transaction's amount.
     *
     * @var Amount
     */
    protected $amount;

    public function __construct(
        ?string $order_id = null,
        ?string $reference_id = null,
        ?string $token = null,
        ?Amount $amount = null
    ) {
        $this->order_id = $order_id;
        $this->reference_id = $reference_id;
        $this->token = $token;
        $this->amount = $amount;
    }

    public function matches(Transaction $transaction): bool
    {
        foreach ($this->asArray() as $key => $value) {
            if ($transaction[$key] instanceof Amount && $value instanceof Amount) {
                return $transaction[$key]->equals($value);
            } elseif ($transaction[$key] != $value) {
                return false;
            }
        }

        return true;
    }

    public function asArray(): array
    {
        $fields = [];
        if (! empty($this->order_id)) {
            $fields['order_id'] = $this->order_id;
        }
        if (! empty($this->reference_id)) {
            $fields['reference_id'] = $this->reference_id;
        }
        if (! empty($this->token)) {
            $fields['token'] = $this->token;
        }
        if (! empty($this->amount)) {
            $fields['amount'] = $this->amount;
        }

        return $fields;
    }
}
