<?php

namespace Parsisolution\Gateway\Providers\Vandar;

use Parsisolution\Gateway\Exceptions\TransactionException;

class VandarException extends TransactionException
{

    /**
     * returns an associative array of `code` => `message`
     *
     * @return array
     */
    protected function getErrors()
    {
        return [
//            400 => "Bad Request",
//            401 => "Unauthorized",
//            403 => "Forbidden",
//            404 => "Not Found",
//            405 => "Method Not Allowed",
//            406 => "Not Acceptable -- You requested a format that isn't json",
//            410 => "Gone",
//            422 => "Bad Request",
//            429 => "Too Many Requests",
//            500 => "Internal Server Error",
//            503 => "Service Unavailable -- We're temporarially offline for maintanance. Please try again later.",

            400 => 'درخواست شما از سرویس وندار اشتباه است',
            401 => 'یا توکن را در درخواست خود ارسال نکردید یا توکن شما معتبر نمی باشد',
            403 => 'شما دسترسی لازم برای دریافت این پاسخ را ندارید',
            404 => 'درخواست ارسال شده با این آدرس در سرویس وندار موجود نیست',
            405 => 'آدرس ارسال شده توسط شما با متد آن همخوانی ندارد لطفا با توجه به مستندات متد خود را اصلاح کنید',
            406 => 'ورودی فرستاده شده از سمت شما برای سرویس وندار باید به فرمت json باشد لطفا فرمت ورودی را اصلاح کنید',
            410 => 'درخواست ارسال شده از سرویس وندار حذف شده است',
            422 => 'یکی از فیلدهایی که برای سرویس ارسال کرده اید اشتباه است',
            429 => 'تعداد درخواست های ارسال شده از سمت شما برای سرویس ما قابل پاسخگویی نیست، لطفا کمی صبر کنید و دوباره درخواست خود را ارسال کنید',
            500 => 'خطای نامشخصی در سرور رخ داده است لطفا کمی صبر کنید و دوباره تلاش کنید',
            503 => 'سرویس وندار در حال حاضر موقتا در دسترس نیست، لطفا کمی صبر کنید و دوباره تلاش کنید',
        ];
    }
}
