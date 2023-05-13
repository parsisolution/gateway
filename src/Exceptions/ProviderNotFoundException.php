<?php

namespace Parsisolution\Gateway\Exceptions;

class ProviderNotFoundException extends GatewayException
{
    protected $code = -101;

    protected $message = "Provided info doesn't map into a supported gateway provider.";
}
