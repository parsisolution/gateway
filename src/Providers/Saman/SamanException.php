<?php

namespace Parsisolution\Gateway\Providers\Saman;

use Parsisolution\Gateway\Exceptions\TransactionException;

class SamanException extends TransactionException
{

    /**
     * returns an associative array of `code` => `message`
     *
     * @return array
     */
    protected function getErrors()
    {
        $errors = [
            'CanceledByUser'             => 'کاربر انصراف داده است',
            'OK'                         => 'پرداخت با موفقیت انجام شد',
            'Failed'                     => 'پرداخت انجام نشد.',
            'SessionIsNull'              => 'کاربر در بازه زمانی تعیین شده پاسخی ارسال نکرده است.',
            'InvalidParameters'          => 'پارامترهای ارسالی نامعتبر است.',
            'MerchantIpAddressIsInvalid' => 'آدرس سرور پذیرنده نامعتبر است (در پرداخت‌های بر پایه توکن)',
            'TokenNotFound'              => 'توکن ارسال شده یافت نشد.',
            'TokenRequired'              => 'با این شماره ترمینال فقط تراکنش‌های توکنی قابل پرداخت هستند.',
            'TerminalNotFound'           => 'شماره ترمینال ارسال شده یافت نشد.',

            -1  => "خطای در پردازش اطلاعات ارسالی. (مشکل در یکی از ورودی‌ها و ناموفق بودن فراخوانی متد برگشت تراکنش)",
            -2  => "سپرده ها برابر نیستند",
            -3  => "ورودی ها حاوی کاراکترهای غیر مجاز می باشند",
            -4  => "Merchant Authentication Failed (کلمه عبور یا کد فروشنده اشتباه است)",
            -5  => "Database Exception",
            -6  => "تراکنش قبلا برگشت داده شده است",
            -7  => "رسید دیجیتالی تهی است",
            -8  => "طول ورودی‌ها بیشتر از حد مجاز است",
            -9  => "وجود کاراکترهای غیر مجاز در مبلغ برگشتی",
            -10 => "رسید دیجیتالی به صورت Base64 نیست (حاوی کارکترهای غیرمجاز است)",
            -11 => "طول ورودی‌ها کمتر از حد مجاز است",
            -12 => "مبلغ برگشتی منفی است",
            -13 => "مبلغ برگشتی برای برگشت جزئی بیش از مبلغ برگشت نخورده‌ی رسید دیجیتالی است",
            -14 => "چنین تراکنشی تعریف نشده است",
            -15 => "مبلغ برگشتی به صورت اعشاری داده شده است",
            -16 => "خطای داخلی سیستم",
            -17 => "برگشت زدن جزئی تراکنش مجاز نمی باشد",
            -18 => "IP Address فروشنده نا معتبر است",
        ];

        return array_replace($errors, [
            1  => $errors['CanceledByUser'],
            2  => $errors['OK'],
            3  => $errors['Failed'],
            4  => $errors['SessionIsNull'],
            5  => $errors['InvalidParameters'],
            8  => $errors['MerchantIpAddressIsInvalid'],
            10 => $errors['TokenNotFound'],
            11 => $errors['TokenRequired'],
            12 => $errors['TerminalNotFound'],
        ]);
    }
}
