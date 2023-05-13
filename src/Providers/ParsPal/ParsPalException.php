<?php

namespace Parsisolution\Gateway\Providers\ParsPal;

use Parsisolution\Gateway\Exceptions\TransactionException;

class ParsPalException extends TransactionException
{
    /**
     * returns an associative array of `code` => `message`
     *
     * @return array
     */
    protected function getErrors()
    {
        $errors = [
            200 => 'OK - دریافت موفق درخواست',
            400 => 'Bad Request - خطا در اطلاعات یا انجام',
            401 => 'Unauthorized - دسترسی غیر مجاز و یا عدم ارسال ApiKey',

            'UNAUTHORIZED'          => 'توکن شناسایی ApiKey ارسال نشده است',
            'INVALID_APIKEY'        => 'توکن شناسایی ApiKey صحیح نمی باشد',
            'INVALID_AMOUNT'        => 'مبلغ ارسالی صحیح نمی باشد',
            'INVALID_RETURN_URL'    => 'مسیر بازگشت ارسالی صحیح نمی باشد',
            'INVALID_CURRENCY'      => 'واحد پول ارسالی معتبر نمی باشد',
            'INVALID_IP'            => 'آی پی ارسال کننده درخواست معتبر نمی باشد',
            'INACTIVE_GATEWAY'      => 'درگاه/حساب غیر فعال است',
            'BLOCKED_GATEWAY'       => 'درگاه/حساب مسدود شده است',
            'NOTREADY_GATEWAY'      => 'درگاه راه اندازی نشده است',
            'SERVICE_NOT_AVAILABLE' => 'در حال حاضر سرویس در دسترس نمی باشد',
            'BAD_REQUEST'           => 'محتوایی برای درخواست ارسال نشده است',
            'UNKNOWN_ERROR'         => 'خطای تعریف نشده',

            100 => 'کاربر عملیات پرداخت را انجام داده است',
            99  => 'انصراف کاربر از پرداخت',
            88  => 'پرداخت ناموفق',
            77  => 'لغو پرداخت توسط کاربر',

            'ACCEPTED'               => 'پذیرش موفقیت آمیز درخواست',
            'FAILED'                 => 'عدم پذیرش درخواست',
            'SUCCESSFUL'             => 'تایید شماره رسید پرداخت',
            'VERIFIED'               => 'شماره رسید قبلا تایید گردیده است',
            'AMOUNT_NOT_MATCH'       => 'مبلغ اعلامی جهت تایید پرداخت با مبلغ پرداخت شده همخوانی ندارد',
            'INVALID_RECEIPT_NUMBER' => 'شماره رسید پرداخت معتبر نمی باشد',
            'INVALID_DATA'           => 'عدم همخوانی یا نداشتن صحت اطلاعات',
        ];

        return array_replace($errors, [
            -2 => $errors['UNAUTHORIZED'],
            -1 => $errors['INVALID_APIKEY'],
            1  => $errors['INVALID_AMOUNT'],
            2  => $errors['INVALID_RETURN_URL'],
            3  => $errors['INVALID_CURRENCY'],
            4  => $errors['INVALID_IP'],
            10 => $errors['INACTIVE_GATEWAY'],
            11 => $errors['BLOCKED_GATEWAY'],
            12 => $errors['NOTREADY_GATEWAY'],
            20 => $errors['SERVICE_NOT_AVAILABLE'],
            30 => $errors['BAD_REQUEST'],
            99 => $errors['UNKNOWN_ERROR'],
        ]);
    }
}
