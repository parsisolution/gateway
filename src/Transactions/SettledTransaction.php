<?php

namespace Parsisolution\Gateway\Transactions;

class SettledTransaction extends AbstractTransaction
{

    /**
     * The transaction's tracking code.
     *
     * @var string
     */
    protected $trackingCode;

    /**
     * The transaction's card number.
     *
     * @var string
     */
    protected $cardNumber;

    /**
     * SettledTransaction constructor.
     *
     * @param AuthorizedTransaction $transaction
     * @param string $trackingCode
     * @param string $cardNumber
     * @param array $extraFields
     */
    public function __construct(AuthorizedTransaction $transaction, $trackingCode, $cardNumber = '', $extraFields = [])
    {
        $this->setRaw($transaction->getRaw());
        $this['trackingCode'] = $trackingCode;
        $this['cardNumber'] = $cardNumber;
        $this->map([
            'amount'       => $transaction->getAmount(),
            'extra'        => array_merge($transaction->getExtra(), $extraFields),
            'id'           => $transaction->getId(),
            'referenceId'  => $transaction->getReferenceId(),
            'trackingCode' => $trackingCode,
            'cardNumber'   => $cardNumber,
        ]);
    }

    /**
     * Get the tracking code of transaction.
     *
     * @return string
     */
    public function getTrackingCode()
    {
        return $this->trackingCode;
    }

    /**
     * Get the card number of transaction.
     *
     * @return string
     */
    public function getCardNumber()
    {
        return $this->cardNumber;
    }

    /**
     * Get the reference id of the transaction.
     *
     * @return string
     */
    public function getReferenceId()
    {
        return $this['referenceId'];
    }

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
