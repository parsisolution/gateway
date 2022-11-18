<?php

namespace Parsisolution\Gateway\Providers\Fanava;

use Parsisolution\Gateway\Exceptions\TransactionException;

class FanavaException extends TransactionException
{

    /**
     * returns an associative array of `code` => `message`
     *
     * @return array
     */
    protected function getErrors()
    {
        return [
            -1 => 'تراکنش اصلی یافت نشد',
            -2 => 'تراکنش قبلا Reverse شده است',
            -3 => 'Total Error خطای عمومی - خطای Exception ها',
            -4 => 'امکان انجام درخواست برای این تراکنش وجود ندارد',
            -5 => 'دسترسی غیر مجاز، آدرس IP نامعتبر میباشد (IP در لیست آدرس‌های معرفی شده توسط پذیرنده موجود نمی‌باشد)',
            -6 => 'عدم فعال بودن سرویس برگشت تراکنش برای پذیرنده',
        ];
    }
}
