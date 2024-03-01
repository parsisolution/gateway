<?php

namespace Parsisolution\Gateway\Providers\Zibal;

use Parsisolution\Gateway\Exceptions\TransactionException;

class ZibalException extends TransactionException
{
    /**
     * returns an associative array of `code` => `message`
     *
     * @return array
     */
    protected function getErrors()
    {
        return [
            // token generation results
            100 => 'با موفقیت تایید شد.',
            102 => 'merchant یافت نشد.',
            103 => 'merchant غیرفعال',
            104 => 'merchant نامعتبر',
            105 => 'amount بایستی بزرگتر از 1,000 ریال باشد.',
            106 => 'callbackUrl نامعتبر می‌باشد. (شروع با http و یا https)',
            113 => 'amount مبلغ تراکنش از سقف میزان تراکنش بیشتر است.',
            107 => 'percentMode نامعتبر می‌باشد. (تنها 0 و 1 قابل قبول هستند)',
            108 => 'یک یا چند ذی‌نفع در multiplexing_infos نامعتبر می‌باشند. اطلاعات بیشتر',
            109 => 'یک یا چند ذی‌نفع در multiplexing_infos غیرفعال می‌باشند. اطلاعات بیشتر',
            110 => 'id = self در multiplexing_infos وجود ندارد.',
            111 => 'amount با مجموع سهم‌ها در multiplexing_infos برابر نمی‌باشد.',
            112 => 'موجودی کیف‌پول اصلی شما جهت ثبت این سفارش کافی نمی‌باشد. (در صورتی که fee_mode == true )',

            // verify results
            201 => 'قبلا تایید شده.',
            202 => 'سفارش پرداخت نشده یا ناموفق بوده است. جهت اطلاعات بیشتر جدول وضعیت‌ها را مطالعه کنید.',
            203 => 'trackId نامعتبر می‌باشد.',

            // callback status
            -1 => 'در انتظار پردخت',
            -2 => 'خطای داخلی',
            1  => 'پرداخت شده - تاییدشده',
            2  => 'پرداخت شده - تاییدنشده',
            3  => 'لغوشده توسط کاربر',
            4  => '‌شماره کارت نامعتبر می‌باشد.',
            5  => '‌موجودی حساب کافی نمی‌باشد.',
            6  => 'رمز واردشده اشتباه می‌باشد.',
            7  => '‌تعداد درخواست‌ها بیش از حد مجاز می‌باشد.',
            8  => '‌تعداد پرداخت اینترنتی روزانه بیش از حد مجاز می‌باشد.',
            9  => 'مبلغ پرداخت اینترنتی روزانه بیش از حد مجاز می‌باشد.',
            10 => '‌صادرکننده‌ی کارت نامعتبر می‌باشد.',
            11 => '‌خطای سوییچ',
            12 => 'کارت قابل دسترسی نمی‌باشد.',
        ];
    }
}