<?php

namespace Parsisolution\Gateway\Providers\Novin;

use Parsisolution\Gateway\Exceptions\TransactionException;

class NovinException extends TransactionException
{

    /**
     * returns an associative array of `code` => `message`
     *
     * @return array
     */
    protected function getErrors()
    {
        return [
            'erSucceed' => 'سرویس با موفقیت اجرا شد',
        ];
    }
}
