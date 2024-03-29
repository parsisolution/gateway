<?php

namespace Parsisolution\Gateway\Providers\AsanPardakht;

use Parsisolution\Gateway\Exceptions\TransactionException;

class AsanPardakhtException extends TransactionException
{
    /**
     * returns an associative array of `code` => `message`
     *
     * @return array
     */
    protected function getErrors()
    {
        return [

            0  => 'تراکنش با موفقیت انجام شد',
            1  => 'صادرکننده کارت از انجام تراکنش صرف نظر کرد.',
            2  => 'عملیات تاییدیه این تراکنش قبلا با موفقیت صورت پذیرفته است.',
            3  => 'پذیرنده فروشگاهی نامعتبر می باشد',
            4  => 'کارت توسط دستگاه ضبط شود.',
            5  => 'به تراکنش رسیدگی نشد.',
            6  => 'بروز خطا.',
            7  => 'به دلیل شرایط خاص کارت توسط دستگاه ضبط شود',
            8  => 'با تشخیص هویت دارنده ی کارت، تراکنش موفق می باشد.',
            12 => 'تراکنش نامعتبر است.',
            13 => 'مبلغ تراکنش اص حیه نادرست است.',
            14 => 'شماره کارت ارسالی نامعتبر است.(وجود ندارد)',
            15 => 'صادر کننده ی کارت نامعتبر است.(وجود ندارد)',
            16 => 'تراکنش مورد تایید است و اط عات شیار سوم کارت به روز رسانی شود',
            19 => 'تراکنش مجدداً ارسال شود.',
            23 => 'کارمزد ارسالی پذیرنده غیر قابل قبول است.',
            25 => 'تراکنش اصلی یافت نشد.',
            30 => 'قالب پیام دارای اشکال است.',
            31 => 'پذیرنده توسط سوئیچ پشتیبانی نمی شود.',
            33 => 'تاریخ انقضای کارت سپری شده است',
            34 => 'تراکنش اصلی با موفقیت انجام نپذیرفته است.',
            36 => 'کارت محدود شده است کارت توسط دستگاه ضبط شود.',
            38 => 'تعداد دفعات ورود رمز غلط بیش از حد مجاز است',
            39 => 'کارت حساب اعتباری ندارد.',
            40 => 'عملیات درخواستی پشتیبانی نمی گردد.',
            41 => 'کارت مفقودی می باشد. کارت توسط دستگاه ضبط شود.',
            42 => 'کارت حساب عمومی ندارد.',
            43 => 'کارت مسروقه می باشد. کارت توسط دستگاه ضبط شود.',
            44 => 'کارت حساب سرمایه گذاری ندارد.',
            51 => 'موجودی کافی نمی باشد.',
            52 => 'کارت حساب جاری ندارد.',
            53 => 'کارت حساب قرض الحسنه ندارد.',
            54 => 'تاریخ انقضای کارت سپری شده است.',
            55 => 'رمز کارت نامعتبر است.',
            56 => 'کارت نامعتبر است.',
            57 => 'بانک شما این تراکنش را پشتیبانی نمیکند',
            58 => 'انجام تراکنش مربوطه توسط پایانه ی انجام دهنده مجاز نمی باشد.',
            59 => 'کارت مظنون به تقلب است.',
            61 => 'مبلغ تراکنش بیش از حد مجاز می باشد.',
            62 => 'کارت محدود شده است.',
            63 => 'تمهیدات امنیتی نقض گردیده است.',
            64 => 'مبلغ تراکنش اصلی نامعتبر است.( تراکنش مالی اصلی با این مبلغ نمی باشد).',
            65 => ' تعداد درخواست تراکنش بیش از حد مجاز می باشد.',
            67 => ' کارت توسط دستگاه ضبط شود.',
            75 => ' تعداد دفعات ورود رمز غلط بیش از حد مجاز است.',
            77 => ' روز مالی تراکنش نا معتبر است.',
            78 => ' کارت فعال نیست.',
            79 => ' حساب متصل به کارت نامعتبر است یا دارای اشکال است.',
            80 => ' تراکنش موفق عمل نکرده است.',
            84 => 'بانک صادر کننده کارت پاسخ نمیدهد',
            86 => 'موسسه ارسال کننده شاپرک یا مقصد تراکنش در حالت Sign off است.',
            90 => 'بانک صادرکننده کارت درحال انجام عملیات پایان روز میباشد',
            91 => 'بانک صادر کننده کارت پاسخ نمیدهد',
            92 => 'مسیری برای ارسال تراکنش به مقصد یافت نشد. ( موسسه های اع می معتبر نیستند)',
            93 => 'تراکنش با موفقیت انجام نشد. (کمبود منابع و نقض موارد قانونی)',
            94 => 'ارسال تراکنش تکراری.',
            96 => 'بروز خطای سیستمی در انجام تراکنش.',
            97 => ' فرایند تغییر کلید برای صادر کننده یا پذیرنده در حال انجام است.',

            -100 => 'تاریخ ارسالی محلی پذیرنده نامعتبر است',
            -103 => 'مبلغ فاکتور برای پیکربندی فعلی پذیرنده معتبر نمی باشد',
            -106 => 'سرویس وجود ندارد یا برای پذیرنده فعال نیست',
            -109 => 'هیچ آدرس کال بکی برای درخواست پیکربندی نشده است',
            -112 => 'شماره فاکتور نامعتبر یا تکراری است',
            -115 => 'پذیرنده فعال نیست یا پیکربندی پذیرنده غیرمعتبر است',

            301 => 'پیکربندی پذیرنده اینترنتی نامعتبر است',
            302 => 'کلیدهای رمزنگاری نامعتبر هستند',
            303 => 'رمزنگاری نامعتبر است',
            304 => 'تعداد عناصر درخواست نامعتبر است',
            305 => 'نام کاربری یا رمز عبور پذیرنده نامعتبر است',
            306 => 'با آسان پرداخت تماس بگیرید',
            307 => 'سرور پذیرنده نامعتبر است',
            308 => 'شناسه فاکتور می بایست صرفا عدد باشد',
            309 => 'مبلغ فاکتور نادرست ارسال شده است',
            310 => 'طول فیلد تاریخ و زمان نامعتبر است',
            311 => 'فرمت تاریخ و زمان ارسالی پذیرنده نامعتبر است',
            312 => 'نوع سرویس نامعتبر است',
            313 => 'شناسه پرداخت کننده نامعتبر است',
            315 => 'فرمت توصیف شیوه تسهیم شبا نامعتبر است',
            316 => 'شیوه تقسیم وجوه با مبلغ کل تراکنش همخوانی ندارد',
            317 => 'شبا متعلق به پذیرنده نیست',
            318 => 'هیچ شبایی برای پذیرنده موجود نیست',
            319 => 'خطای داخلی. دوباره درخواست ارسال شود',
            320 => 'شبای تکراری در رشته درخواست ارسال شده است',

            400 => 'موفق',
            401 => 'حالت اولیه (مقدار اولیه در شرایط Serialize/Deserialize)',
            402 => 'هویت درخواست کننده نامعتبر است',
            403 => 'تراکنشی یافت نشد',
            404 => 'خطا در پردازش',

            500 => 'بازبینی تراکنش با موفقیت انجام شد',
            501 => 'پردازش هنوز انجام نشده است',
            502 => 'وضعیت تراکنش نامشخص است',
            503 => 'تراکنش اصلی ناموفق بوده است',
            504 => 'قبلا درخواست بازبینی برای این تراکنش داده شده است',
            505 => 'قبلا درخواست تسویه برای این تراکنش ارسال شده است',
            506 => 'قبلا درخواست بازگشت برای این تراکنش ارسال شده است',
            507 => 'تراکنش در لیست تسویه قرار دارد',
            508 => 'تراکنش در لیست بازگشت قرار دارد',
            509 => 'امکان انجام عملیات به سبب وجود مشکل داخلی وجود ندارد',
            510 => 'هویت درخواست کننده عملیات نامعتبر است',

            600 => 'درخواست تسویه تراکنش با موفقیت ارسال شد',
            601 => 'پردازش هنوز انجام نشده است',
            602 => 'وضعیت تراکنش نامشخص است',
            603 => 'تراکنش اصلی ناموفق بوده است',
            604 => 'تراکنش بازبینی نشده است',
            605 => 'قبلا درخواست بازگشت برای این تراکنش ارسال شده است',
            606 => 'قبلا درخواست تسویه برای این تراکنش ارسال شده است',
            607 => 'امکان انجام عملیات به سبب وجود مشکل داخلی وجود ندارد',
            608 => 'تراکنش در لیست منتظر بازگشت ها وجود دارد',
            609 => 'تراکنش در لیست منتظر تسویه ها وجود دارد',
            610 => 'هویت درخواست کننده عملیات نامعتبر است',

            700 => 'درخواست بازگشت تراکنش با موفقیت ارسال شد',
            701 => 'پردازش هنوز انجام نشده است',
            702 => 'وضعیت تراکنش نامشخص است',
            703 => 'تراکنش اصلی ناموفق بوده است',
            704 => 'امکان بازگشت یک تراکنش بازبینی شده وجود ندارد',
            705 => 'قبلا درخواست بازگشت تراکنش برای این تراکنش ارسال شده است',
            706 => 'قبلا درخواست تسویه برای این تراکنش ارسال شده است',
            707 => 'امکان انجام عملیات به سبب وجود مشکل داخلی وجود ندارد',
            708 => 'تراکنش در لیست منتظر بازگشت ها وجود دارد',
            709 => 'تراکنش در لیست منتظر تسویه ها وجود دارد',
            710 => 'هویت درخواست کننده عملیات نامعتبر است',

            1100 => 'موفق',
            1101 => 'هویت درخواست کننده نامعتبر است',
            1102 => 'خطا در پردازش',
            1103 => 'تراکنشی یافت نشد',

        ];
    }
}
