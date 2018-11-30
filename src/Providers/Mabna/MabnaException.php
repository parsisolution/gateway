<?php
/**
 * Created by PhpStorm.
 * User: Ali Ghasemzadeh
 * Date: 11/29/2018
 * Time: 10:39 PM
 */

namespace Parsisolution\Gateway\Providers\Mabna;

use Parsisolution\Gateway\Exceptions\TransactionException;

class MabnaException extends TransactionException
{
    /**
     * returns an associative array of `code` => `message`
     *
     * @return array
     */
    protected function getErrors()
    {
        return [
            -7  => 'تراکنش توسط کاربر لغو شد.',
            -1  => 'تراکنش پیدا نشد.',
            -2  => 'تراکنش قبلا بازگشت داده شده است.',
            -3  => 'Total Error خطای عمومی - خطای Exceptions',
            -4  => 'امکان انجام درخواست برای این تراکنش وجود ندارد.',
            -5  => 'آدرس IP نا معتبر است.',
            -6  => 'عدم فعال بودن سرویس برگشت برای پذیرنده.',
        ];
    }
}