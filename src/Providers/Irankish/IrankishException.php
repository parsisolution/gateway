<?php

namespace Parsisolution\Gateway\Providers\Irankish;

use Parsisolution\Gateway\Exceptions\TransactionException;

class IrankishException extends TransactionException
{
    /**
     * returns an associative array of `code` => `message`
     *
     * @return array
     */
    protected function getErrors()
    {
        return [
            2  => 'تراکنش قبلا برگشت شده است',
            3  => 'پذیرنده فروشگاهی نا معتبر است',
            5  => 'از انجام تراکنش صرف نظر شد',
            14 => 'اطلاعات کارت صحیح نمی باشد',
            17 => 'از انجام تراکنش صرف نظر شد',
            25 => 'تراکنش اصلی یافت نشد',
            30 => 'فرمت پیام نادرست است',
            31 => 'عدم تطابق کد ملی خریدار با دارنده کارت',
            40 => 'عمل درخواستی پشتیبانی نمی شود',
            //            42  => 'کارت یا حساب مبدا در وضعیت پذیرش نمی باشد',
            42 => 'کارت یا حساب مقصد در وضعیت پذیرش نمی باشد',
            51 => 'موجودی حساب کافی نمی باشد',
            54 => 'تاریخ انقضا کارت سررسید شده است',
            55 => 'رمز کارت نادرست است',
            56 => 'اطلاعات کارت یافت نشد',
            57 => 'انجام تراکنش مورد درخواست توسط پایانه انجام دهنده مجاز نمی باشد',
            58 => 'انجام تراکنش مورد درخواست توسط پایانه انجام دهنده مجاز نمی باشد',
            59 => 'اطلاعات کارت صحیح نمی باشد یا کارت مظنون به تقلب است',
            61 => 'مبلغ تراکنش بیش از حد مجاز است',
            62 => 'کارت محدود شده است',
            //            63  => 'کد اعتبار سنجی پیام نا معتبر است',
            63  => 'تمهیدات امنیتی نقض گردیده است',
            64  => 'مبلغ تراکنش نادرست است،جمع مبالغ تقسیم وجوه برابر مبلغ کل تراکنش نمی باشد',
            65  => 'تعداد دفعات انجام تراکنش بیش از حد مجاز است',
            75  => 'تعداد دفعات ورود رمز اشتباه از حد مجاز فراتر رفته است',
            77  => 'روز مالی تراکنش نا معتبر است',
            78  => 'کارت فعال نیست',
            79  => 'حساب متصل به کارت بسته یا دارای اشکال است',
            86  => 'شتاب در حال Sign Off است',
            94  => 'تراکنش تکراری است',
            96  => 'قوانین سامانه نقض گردیده است ، خطای داخلی سامانه',
            97  => 'کد تولید کد اعتبار سنجی نا معتبر است',
            98  => 'سقف استفاده از رمز دوم ایستا به پایان رسیده است',
            901 => 'درخواست نا معتبر است ( Tokenization )',
            902 => 'پارامترهای اضافی درخواست نامعتبر می باشد ( Tokenization )',
            903 => 'شناسه پرداخت نامعتبر می باشد ( Tokenization )',
            904 => 'اطلاعات مرتبط با قبض نا معتبر می باشد ( Tokenization )',
            905 => 'شناسه درخواست نامعتبر می باشد ( Tokenization )',
            906 => 'درخواست تاریخ گذشته است ( Tokenization )',
            907 => 'آدرس بازگشت نتیجه پرداخت نامعتبر می باشد ( Tokenization )',
            909 => 'پذیرنده نامعتبر می باشد ( Tokenization )',
            910 => 'پارامترهای مورد انتظار پرداخت تسهیمی تامین نگردیده است ( Tokenization )',
            911 => 'پارامترهای مورد انتظار پرداخت تسهیمی نا معتبر یا دارای اشکال می باشد ( Tokenization )',
            912 => 'تراکنش درخواستی برای پذیرنده فعال نیست ( Tokenization )',
            913 => 'تراکنش تسهیم برای پذیرنده فعال نیست ( Tokenization )',
            914 => 'آدرس آی پی دریافتی درخواست نا معتبر می باشد',
            915 => 'شماره پایانه نامعتبر می باشد ( Tokenization )',
            916 => 'شماره پذیرنده نا معتبر می باشد ( Tokenization )',
            917 => 'نوع تراکنش اعلام شده در خواست نا معتبر می باشد ( Tokenization )',
            918 => 'پذیرنده فعال نیست ( Tokenization )',
            919 => 'مبالغ تسهیمی ارائه شده با توجه به قوانین حاکم بر وضعیت تسهیم پذیرنده ، نا معتبر است ( Tokenization )',
            920 => 'شناسه نشانه نامعتبر می باشد',
            921 => 'شناسه نشانه نامعتبر و یا منقضی شده است',
            922 => 'نقض امنیت درخواست ( Tokenization )',
            923 => 'ارسال شناسه پرداخت در تراکنش قبض مجاز نیست ( Tokenization )',
            925 => 'مبلغ مبادله شده نا معتبر می باشد',
            928 => 'مبلغ مبادله شده نا معتبر می باشد ( Tokenization )',
            929 => 'شناسه پرداخت ارائه شده با توجه به الگوریتم متناظر نا معتبر می باشد ( Tokenization )',
            930 => 'کد ملی ارائه شده نا معتبر می باشد ( Tokenization )',
        ];
    }
}
