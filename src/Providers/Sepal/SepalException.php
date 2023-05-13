<?php

namespace Parsisolution\Gateway\Providers\Sepal;

use Parsisolution\Gateway\Exceptions\TransactionException;

class SepalException extends TransactionException
{
    /**
     * returns an associative array of `code` => `message`
     *
     * @return array
     */
    protected function getErrors()
    {
        return [
            0 => 'خطای تعریف نشده',
        ];
    }
}
