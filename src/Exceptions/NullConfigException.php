<?php

namespace Parsisolution\Gateway\Exceptions;

class NullConfigException extends GatewayException
{
    protected $code = -106;

    protected $message = 'Config is null. use php artisan to publish config file';
}
