<?php

namespace Parsisolution\Gateway\Exceptions;

use Parsisolution\Gateway\Traits\HasTransaction;

/**
 * Thrown when the user tries to submit a payment request which was submitted before.
 *
 */
class RetryException extends GatewayException
{
    use HasTransaction;

    protected $code = -104;
    protected $message = 'This transaction was processed before.';
}
