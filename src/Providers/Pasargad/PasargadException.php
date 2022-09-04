<?php

namespace Parsisolution\Gateway\Providers\Pasargad;

use Parsisolution\Gateway\Exceptions\TransactionException;

class PasargadException extends TransactionException
{

    /**
     * returns an associative array of `code` => `message`
     *
     * @return array
     */
    protected function getErrors()
    {
        return [
            200 => 'تراکنش ارسالی معتبر نیست',
        ];
    }
}
