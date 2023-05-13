<?php

namespace Parsisolution\Gateway\Exceptions;

class NotFoundTransactionException extends GatewayException
{
    protected $code = -103;

    protected $message = "Didn't find the transaction record on db.";
}
