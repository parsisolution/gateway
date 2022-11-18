<?php

namespace Parsisolution\Gateway\Exceptions;

class GeneralTransactionException extends TransactionException
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
