<?php

namespace Parsisolution\Gateway\Providers\IranDargah;

use Parsisolution\Gateway\Exceptions\TransactionException;

class IranDargahException extends TransactionException
{
    /**
     * returns an associative array of `code` => `message`
     *
     * @return array
     */
    protected function getErrors()
    {
        return [
            200 => 'اتصال به درگاه بانک با موفقیت انجام ‌شده‌است.',
            201 => 'در حال پرداخت در درگاه بانک.',
            100 => 'تراکنش با موفقیت انجام ‌شده‌‌است.',
            101 => 'تراکنش قبلا verify شده‌است.',
            404 => 'تراکنش یافت نشد.',
            403 => 'کد مرچنت صحیح نمی‌باشد.',
            -1  => 'کاربر از انجام تراکنش منصرف‌ شده‌است.',
            -2  => 'اطلاعات ارسالی صحیح نمی‌باشد.',
            -10 => 'مبلغ تراکنش کمتر از 10,000 ریال است.',
            -11 => 'مبلغ تراکنش با مبلغ پرداخت، یکسان نیست. مبلغ برگشت‌خورد.',
            -12 => 'شماره کارتی که با آن، تراکنش انجام ‌شده‌است با شماره کارت ارسالی، مغایرت دارد. مبلغ برگشت‌خورد.',
            -13 => 'تراکنش تکراری است.',
            -20 => 'شناسه تراکنش یافت‌‌نشد.',
            -21 => 'مدت زمان مجاز، جهت ارسال به بانک گذشته‌است.',
            -22 => 'تراکنش برای بانک ارسال شده‌است.',
            -23 => 'خطا در اتصال به درگاه بانک.',
            -30 => 'اشکالی در فرایند پرداخت ایجاد ‌شده‌است.مبلغ برگشت خورد.',
            -31 => 'خطای ناشناخته',

            'CONNECTION_ERROR' => 'خطا در اتصال به درگاه پرداخت ایران درگاه',
            'TOO_MUCH_AMOUNT'  => 'مبلغ از میزان مجاز بیشتر است',
            'UNKNOWN_ERROR'    => 'خطای ناشناخته',
        ];
    }
}
