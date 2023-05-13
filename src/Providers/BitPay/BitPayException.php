<?php

namespace Parsisolution\Gateway\Providers\BitPay;

use Parsisolution\Gateway\Exceptions\TransactionException;

class BitPayException extends TransactionException
{
    /**
     * returns an associative array of `code` => `message`
     *
     * @return array
     */
    protected function getErrors()
    {
        return [
            0  => 'خطای تعریف نشده',
            -1 => 'API ارسالی با نوع API تعریف شده در bitpay سازگار نیست',
            -2 => 'مقدار amount داده عددي نمی‌باشد و یا کمتر از ۱۰۰۰ ریال است',
            -3 => 'مقدار redirect رشته null است',
            -4 => 'درگاهی با اطالعات ارسالی شما وجود ندارد و یا در حالت انتظار می‌باشد',
            -5 => 'خطا در اتصال به درگاه، لطفا مجددا تلاش کنید',
        ];
    }
}
