<?php

namespace Parsisolution\Gateway\Providers\AsanPardakht;

use Parsisolution\Gateway\Exceptions\TransactionException;

class AsanPardakhtRestException extends TransactionException
{

    /**
     * returns an associative array of `code` => `message`
     *
     * @return array
     */
    protected function getErrors()
    {
        return [

            200 => "موفقیت آمیز",

            400 => "درخواست اشتباه",
            401 => "پارامتر های غیر مجاز در هدر",
            471 => "تراکنش انجام نشد",
            472 => "درخواست تایید راکنش قبلاً داده شده است",
            473 => "درخواست اصلاح تراکنش قبلاً داده شده است",
            474 => "درخواست تراکنش برای بازگشت",
            475 => "درخواست اصلاح تراکنش در لیست قرار گرفته است",
            476 => "درخواست بازگشت تراکتش در لیست فرار گرفته است",
            477 => "هویت مورد اعتماد برای ادامه کار نیست",
            478 => "درخواست قبلا لغو شده است",

            571 => "هنوز پردازش نشده است",
            572 => "وضعیت تراکنش هنور مشخص نیست",
            573 => "به دلیل خطای داخلی قادر به درخواست تأیید نیست",

        ];
    }
}
