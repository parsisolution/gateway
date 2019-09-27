<?php

namespace Parsisolution\Gateway\Providers\Sizpay;

use Parsisolution\Gateway\Exceptions\TransactionException;

class SizpayException extends TransactionException
{

    /**
     * returns an associative array of `code` => `message`
     *
     * @return array
     */
    protected function getErrors()
    {
        return [
            0  => 'موفق',
            106 => 'مبلغ پرداختی باید بین 10,000 و 50,000,000 ریال باشد.',
            128 => 'نام کاربری و یا رمزعبور نامعتبر می باشد.',
        ];
    }
}
