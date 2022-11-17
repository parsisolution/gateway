<?php

namespace Parsisolution\Gateway\Exceptions;

use Parsisolution\Gateway\Traits\HasTransaction;

class InvalidRequestException extends GatewayException
{
    use HasTransaction;

    protected $code = -102;
    protected $message = 'Request parameters are not valid.';
}
