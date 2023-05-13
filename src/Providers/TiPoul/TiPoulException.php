<?php

namespace Parsisolution\Gateway\Providers\TiPoul;

use Parsisolution\Gateway\Exceptions\TransactionException;

class TiPoulException extends TransactionException
{
    /**
     * returns an associative array of `code` => `message`
     *
     * @return array
     */
    protected function getErrors()
    {
        return [
            -1 => 'خطای عمومی',
        ];
    }
}
