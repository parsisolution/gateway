<?php

namespace Parsisolution\Gateway\Providers\Payir;

use Parsisolution\Gateway\Exceptions\TransactionException;


class PayirReceiveException extends TransactionException {

    /**
     * returns an associative array of `code` => `message`
     *
     * @return array
     */
    protected function getErrors()
    {
        return [
            -1 => 'ارسال api الزامی می باشد',
            -2 => 'ارسال transId الزامی می باشد',
            -3 => 'درگاه پرداختی با api ارسالی یافت نشد و یا غیر فعال می باشد',
            -4 => 'فروشنده غیر فعال می باشد',
            -5 => 'تراکنش با خطا مواجه شده است',
        ];
    }
}
