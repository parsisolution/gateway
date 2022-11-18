<?php

namespace Parsisolution\Gateway\Providers\Sepehr;

use Parsisolution\Gateway\Exceptions\TransactionException;

class SepehrException extends TransactionException
{

    /**
     * returns an associative array of `code` => `message`
     *
     * @return array
     */
    protected function getErrors()
    {
        return [
            -1 => 'تراکنش پیدا نشد',
            -2 => 'در زمان دریافت توکن به دلیل عدم وجود (عدم تطابق) IP'.
                ' و یا به دلیل بسته بودن خروجی پورت 8081 از سمت Host این خطا ایجاد میگردد.'.
                ' تراکنش قبلا Reverse شده است.',
            -3 => 'Total Error خطای عمومی – خطای Exception ها',
            -4 => 'امکان انجام درخواست برای این تراکنش وجود ندارد',
            -5 => 'آدرس IP نامعتبر میباشد (IP در لیست آدرسهای معرفی شده توسط پذیرنده موجود نمیباشد)',
            -6 => 'عدم فعال بودن سرویس برگشت تراکنش برای پذیرنده',
        ];
    }
}
