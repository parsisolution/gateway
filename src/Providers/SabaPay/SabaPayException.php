<?php

namespace Parsisolution\Gateway\Providers\SabaPay;

use Parsisolution\Gateway\Exceptions\TransactionException;

class SabaPayException extends TransactionException
{
    /**
     * returns an associative array of `code` => `message`
     *
     * @return array
     */
    protected function getErrors()
    {
        return [
            100 => 'نوع درخواست باید POST باشد.',
            101 => 'API KEY ارسال نشده است یا صحیح نیست.',
            102 => 'مبلغ ارسال نشده است یا کمتر از 1000 ریال است.',
            103 => 'آدرس بازگشت ارسال نشده است.',
            201 => 'پرداخت انجام نشده است.',
            202 => 'پرداخت کنسل شده است یا در مراحل پرداخت خطایی رخ داده است.',
            200 => 'شناسه پرداخت صحیح نیست.',
            301 => 'خطایی در برقراری ارتباط با سرور بانک رخ داده است.',
        ];
    }
}
