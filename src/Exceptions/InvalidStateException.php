<?php

namespace Parsisolution\Gateway\Exceptions;

/**
 * Thrown when the request state doesn't match the session state.
 */
class InvalidStateException extends GatewayException
{
    protected $code = -105;
    protected $message = "Request doesn't have required state parameter (or it doesn't match session's state).";
}
