<?php

namespace Parsisolution\Gateway\Exceptions;

use Illuminate\Support\Arr;
use Parsisolution\Gateway\Traits\HasTransaction;
use Throwable;

abstract class TransactionException extends \Exception
{
    use HasTransaction;

    /**
     * returns an associative array of `code` => `message`
     *
     * @return array
     */
    abstract protected function getErrors();

    /**
     * Get error code's associate message if exist and code itself otherwise
     *
     * @param  mixed  $code
     * @param  null|string  $message
     * @return string
     */
    protected function getMessageFromCode($code, $message)
    {
        if (isset($message)) {
            return $message;
        }

        $code = strval($code);

        return Arr::get($this->getErrors(), $code, $code);
    }

    /**
     * TransactionException constructor.
     *
     * @param  mixed  $code
     * @param  null|string  $message
     */
    public function __construct($code = 0, $message = null, Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->code = $code;
        $this->message = $this->getMessageFromCode($code, $message);
    }
}
