<?php

namespace Parsisolution\Gateway\Providers\Shepa;

use Parsisolution\Gateway\Exceptions\TransactionException;

class ShepaException extends TransactionException
{

    /**
     * returns an associative array of `code` => `message`
     *
     * @return array
     */
    protected function getErrors()
    {
        return [];
    }
}
