<?php

namespace Parsisolution\Gateway\Providers\AsanPardakht;

use Parsisolution\Gateway\Exceptions\TransactionException;

class AsanPardakhtRestTokenException extends TransactionException
{
    /**
     * returns an associative array of `code` => `message`
     *
     * @return array
     */
    protected function getErrors()
    {
        return [

            200 => 'موفقیت آمیز',

            400 => 'ارسال پارامتر های اشتباه',
            401 => 'پارامتر های غیر مجاز در هدر',
            471 => 'هویت معتبر برای ادامه کار یافت نشد',
            472 => 'هیچ رکوردی یافت نشد',
            473 => 'نام کاربر یا رمز عبور ویا مرچنت اشتباه میباشد',
            474 => 'آیپی شما مجاز نمی باشد',
            475 => 'شناسه فاکتور معتبر نمی باشد',
            476 => 'مقدار مبلغ معتبر نمی باشد',
            477 => 'مقدار تاریخ معتبر نمی باشد',
            478 => 'فرمت تاریخ ارسالی معتبر نمی باشد',
            479 => 'شناسه خدمات معتبر نمی باشد',
            480 => 'شناسه پرداخت کننده معتبر نمی باشد',
            481 => 'فرمت حساب تسهیمی نامعتبر می باشد',
            482 => 'مقدار حساب تسهیمی با کل مبلغ انطباق ندارد',
            483 => 'شماره شبا نامعتبر می باشد',
            484 => 'خطای بانکی',
            485 => 'تاریخ محلی نامعتبر می باشد',
            486 => 'مبلغ مورد نظر در محدوده بانکی مجاز این درگاه نمی‌باشد، لطفا از درگاه دیگری استفاده کنید',
            487 => 'سرویسی برای این شناسه یافت نشد',
            488 => 'لینک برگشتی معتبر نمی باشد',
            489 => 'این شماره فاکتور تکراری می باشد',
            490 => 'شناسه غیر فعال و یا اشتباه می باشد',
            491 => 'حساب های تسهیمی زیاد می باشد',
            492 => 'درخواست غیرقابل پردازش',
            493 => 'درخواست غیرقابل پردازش',

            571 => 'پیکربندی اشتباه',
            572 => 'پیکربندی اشتباه',
            573 => 'سو استفاده آیپی های معتبر برای پیکر بندی سرویس',
            574 => 'خطا در اعتبار سنجی',
            575 => 'شماره شبای معتبری برای این شناسه یافت نشد',
            576 => 'خطای سیستمی',
            577 => 'خطای سیستمی',
            578 => 'هیچ اشتراکی برای این شناسه تعریف نشده است',
            579 => 'نمی توانید بدون شماره شبا درخواستی ارسال کنید',
            580 => 'پردازش خطابرای درخواست های خاص',

        ];
    }
}
