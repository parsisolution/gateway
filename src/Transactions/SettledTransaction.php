<?php

namespace Parsisolution\Gateway\Transactions;

use Parsisolution\Gateway\Contracts\HasId;
use Parsisolution\Gateway\Traits\HasIdField;
use Parsisolution\Gateway\Traits\HasOrderIdField;

class SettledTransaction extends AbstractTransaction implements HasId
{

    use HasIdField, HasOrderIdField;

    /**
     * The transaction's trace number.
     *
     * @var string
     */
    protected $trace_number;

    /**
     * The transaction's card number.
     *
     * @var string
     */
    protected $card_number;

    /**
     * The transaction's Retrieval Reference Number.
     *
     * @var string
     */
    protected $rrn;

    /**
     * SettledTransaction constructor.
     *
     * @param AuthorizedTransaction $transaction
     * @param string $traceNumber
     * @param string $cardNumber
     * @param string $RRN
     * @param array $extraFields
     * @param string $referenceId
     */
    public function __construct(
        AuthorizedTransaction $transaction,
        $traceNumber,
        $cardNumber = '',
        $RRN = '',
        $extraFields = [],
        $referenceId = ''
    ) {
        $this->setRaw($transaction->getRaw());
        if (! empty($referenceId)) {
            $this['reference_id'] = $referenceId;
        }
        $this['trace_number'] = $traceNumber;
        $this['card_number'] = $cardNumber;
        $this['rrn'] = $RRN;
        $this->map([
            'amount'       => $transaction->getAmount(),
            'extra'        => array_merge($transaction->getExtra(), $extraFields),
            'trace_number' => $traceNumber,
            'card_number'  => $cardNumber,
            'rrn'          => $RRN,
        ]);
    }

    /**
     * Get the trace number of transaction.
     *
     * @return string
     */
    public function getTraceNumber()
    {
        return $this->trace_number;
    }

    /**
     * Get the card number of transaction.
     *
     * @return string
     */
    public function getCardNumber()
    {
        return $this->card_number;
    }

    /**
     * Get the Retrieval Reference Number of transaction.
     *
     * @return string
     */
    public function getRRN()
    {
        return $this->rrn;
    }

    /**
     * Get the reference id of the transaction.
     *
     * @return string
     */
    public function getReferenceId()
    {
        return $this['reference_id'];
    }
}
