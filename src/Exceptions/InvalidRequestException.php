<?php

namespace Parsisolution\Gateway\Exceptions;

class InvalidRequestException extends GatewayException
{
    protected $code = -102;
    protected $message = 'Request parameters are not valid.';
}
