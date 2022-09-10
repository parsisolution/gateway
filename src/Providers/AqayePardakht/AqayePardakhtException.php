<?php

namespace Parsisolution\Gateway\Providers\AqayePardakht;

use Parsisolution\Gateway\Exceptions\TransactionException;

class AqayePardakhtException extends TransactionException
{

    /**
     * returns an associative array of `code` => `message`
     *
     * @return array
     */
    protected function getErrors()
    {
        return [
            0 => 'پرداخت انجام نشد',
            1 => 'پرداخت با موفقیت انجام شد',
            2 => 'تراکنش قبلا وریفای شده است',

            -1  => 'amount نمی تواند خالی باشد',
            -2  => 'کد پین درگاه نمی تواند خالی باشد',
            -3  => 'callback نمی تواند خالی باشد',
            -4  => 'amount باید عددی باشد',
            -5  => 'amount باید بین 100 تا 50,000,000 تومان باشد',
            -6  => 'کد پین درگاه اشتباه هست',
            -7  => 'transid نمی تواند خالی باشد',
            -8  => 'تراکنش مورد نظر وجود ندارد',
            -9  => 'کد پین درگاه با درگاه تراکنش مطابقت ندارد',
            -10 => 'مبلغ با مبلغ تراکنش مطابقت ندارد',
            -11 => 'درگاه درانتظار تایید و یا غیر فعال است',
            -12 => 'امکان ارسال درخواست برای این پذیرنده وجود ندارد',
            -13 => 'شماره کارت باید 16 رقم چسبیده بهم باشد',
        ];
    }
}
