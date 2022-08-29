<?php

namespace Parsisolution\Gateway\Transactions;

use Parsisolution\Gateway\Contracts\HasId;
use Parsisolution\Gateway\Traits\HasIdField;
use Parsisolution\Gateway\Traits\HasOrderIdField;

class SettledTransaction extends AbstractTransaction implements HasId
{
    use HasIdField, HasOrderIdField;

    /**
     * SettledTransaction constructor.
     *
     * @param AuthorizedTransaction $transaction
     * @param string $traceNumber
     * @param FieldsToMatch $toMatch
     * @param string $cardNumber
     * @param string $RRN
     * @param array $extraFields
     * @param string $referenceId
     */
    public function __construct(
        AuthorizedTransaction $transaction,
        $traceNumber,
        $toMatch,
        $cardNumber = '',
        $RRN = '',
        $extraFields = [],
        $referenceId = ''
    ) {
        $this->setAttributes($transaction->getAttributes());
        $this['trace_number'] = $traceNumber;
        $this['to_match'] = $toMatch;
        $this['card_number'] = $cardNumber;
        $this['rrn'] = $RRN;
        $this['extra'] = array_merge($transaction->getExtra(), $extraFields);
        if (! empty($referenceId)) {
            $this['reference_id'] = $referenceId;
        }
    }

    /**
     * Get the trace number of transaction.
     *
     * @return string
     */
    public function getTraceNumber()
    {
        return $this['trace_number'];
    }

    /**
     * Get fields to match.
     *
     * @return FieldsToMatch
     */
    public function getFieldsToMatch()
    {
        return $this['to_match'];
    }

    /**
     * Get the card number of transaction.
     *
     * @return string
     */
    public function getCardNumber()
    {
        return $this['card_number'];
    }

    /**
     * Get the Retrieval Reference Number of transaction.
     *
     * @return string
     */
    public function getRRN()
    {
        return $this['rrn'];
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
