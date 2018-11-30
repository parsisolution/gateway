<?php

namespace Parsisolution\Gateway\Exceptions;

/**
 * Thrown when the user tries to submit a payment request which was submitted before.
 *
 */
class RetryException extends GatewayException
{
    protected $code = -104;
    protected $message = 'This transaction was processed before.';
}
