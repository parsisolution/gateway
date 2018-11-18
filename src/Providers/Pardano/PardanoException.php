<?php

namespace Parsisolution\Gateway\Providers\Pardano;

use Parsisolution\Gateway\Exceptions\TransactionException;


class PardanoException extends TransactionException {

    /**
     * returns an associative array of `code` => `message`
     *
     * @return array
     */
    protected function getErrors()
    {
        return [
            -1  => 'api نامعتبر است',
            -2  => 'مبلغ از کف تعریف شده کمتر است',
            -3  => 'مبلغ از سقف تعریف شده بیشتر است',
            -4  => 'مبلغ نامعتبر است',
            -6  => 'درگاه غیرفعال است',
            -7  => 'آی پی شما مسدود است',
            -9  => 'آدرس کال بک خالی است ',
            -10 => 'چنین تراکنشی یافت نشد',
            -11 => 'تراکنش انجام نشده ',
            -12 => 'تراکنش انجام شده اما مبلغ نادرست است ',
        ];
    }
}