<?php

namespace Parsisolution\Gateway\Providers\Jibimo;

use Parsisolution\Gateway\Exceptions\TransactionException;

class JibimoException extends TransactionException
{

    /**
     * returns an associative array of `code` => `message`
     *
     * @return array
     */
    protected function getErrors()
    {
        return [
            0   => 'کاربر هنوز وارد درگاه نشده',
            -3  => 'کاربر در درگاه بانکی روی انصراف کلیک کرده است',
            -4  => 'خطا در پرداخت (با پشتیبانی تماس بگیرید)',
            99  => 'پول از حساب کاربر برداشت شده و منتظر verify شدن است',
            100 => 'تراکنش با موفقیت تایید شد',
            101 => 'تراکنش قبلاً تایید شده است',
        ];
    }
}
