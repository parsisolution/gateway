<?php

namespace Parsisolution\Gateway\Transactions;

/**
 * Class Amount
 *
 * payment amount with break-ups.
 *
 * @property string currency
 * @property float total
 */
class Amount
{

    /**
     * @var string
     */
    private $currency;

    /**
     * @var float
     */
    private $total;

    /**
     * Amount constructor.
     * <p></p>
     * in currency use
     * `IRR` for `Riyal` and `IRT` for `Toman`
     * <p></p>
     * 3-letter [currency code](https://developers.braintreepayments.com/reference/general/currencies).
     * each Gateway does not support all currencies.
     *
     * @param float $amount
     * @param string $currency default to Iran Toman
     * <p></p>
     * (IRR) for Riyal and (IRT) for Toman
     * <p></p>
     * 3-letter currency code. each Gateway does not support all currencies.
     */
    public function __construct($amount, $currency = 'IRT')
    {
        $this->total = $amount;
        $this->currency = $currency;
    }

    /**
     * `IRR` for `Riyal` and `IRT` for `Toman`
     * <p></p>
     * 3-letter [currency code](https://developers.braintreepayments.com/reference/general/currencies).
     * each Gateway does not support all currencies.
     *
     * @param string $currency
     *
     * @return self
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * `IRR` for `Riyal` and `IRT` for `Toman`
     * <p></p>
     * 3-letter [currency code](https://developers.braintreepayments.com/reference/general/currencies).
     * each Gateway does not support all currencies.
     *
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * Total amount charged from the payer to the payee.
     * In case of a refund, this is the refunded amount to the original payer from the payee.
     * 10 characters max with support for 2 decimal places.
     *
     * @param float $total
     *
     * @return self
     */
    public function setTotal($total)
    {
        $this->total = $total;

        return $this;
    }

    /**
     * Total amount charged from the payer to the payee.
     * In case of a refund, this is the refunded amount to the original payer from the payee.
     * 10 characters max with support for 2 decimal places.
     *
     * @return float
     */
    public function getTotal()
    {
        return $this->total;
    }

    /**
     * return Iran currency by Riyal
     *
     * @return float
     * @throws \BadMethodCallException
     */
    public function getRiyal()
    {
        switch ($this->currency) {
            case 'IRR':
                return floor($this->total);
            case 'IRT':
                return floor($this->total * 10);
        }
        throw new \BadMethodCallException("only Iran currencies can cast to Riyal");
    }

    /**
     * return Iran currency by Toman
     *
     * @return float
     * @throws \BadMethodCallException
     */
    public function getToman()
    {
        switch ($this->currency) {
            case 'IRR':
                return floor($this->total / 10);
            case 'IRT':
                return floor($this->total);
        }
        throw new \BadMethodCallException("only Iran currencies can cast to Toman");
    }
}
