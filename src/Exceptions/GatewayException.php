<?php

namespace Parsisolution\Gateway\Exceptions;

abstract class GatewayException extends \Exception
{
    protected $code = -100;
    protected $message = 'Gateway Exception Happened';
}
