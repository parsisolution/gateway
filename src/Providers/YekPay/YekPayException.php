<?php

namespace Parsisolution\Gateway\Providers\YekPay;

use Parsisolution\Gateway\Exceptions\TransactionException;

class YekPayException extends TransactionException
{
    /**
     * returns an associative array of `code` => `message`
     *
     * @return array
     */
    protected function getErrors()
    {
        return [
            -1   => 'The parameters are incomplete',
            -2   => 'Merchant code is incorrect',
            -3   => 'Merchant code is not active',
            -4   => 'Currencies is not valid',
            -5   => 'Maximum/Minimum amount is not valid',
            -6   => 'Your IP is restricted',
            -7   => 'Order id must be unique',
            -8   => 'Currencies is not valid',
            -9   => 'Maximum/Minimum amount is not valid',
            -10  => 'Your IP is restricted',
            -100 => 'Unknown error',
            100  => 'Success',
        ];
    }
}
