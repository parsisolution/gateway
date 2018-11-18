<?php

namespace Parsisolution\Gateway\Exceptions;

use Illuminate\Support\Arr;
use Throwable;


abstract class TransactionException extends \Exception {

    /**
     * returns an associative array of `code` => `message`
     *
     * @return array
     */
    abstract protected function getErrors();

    /**
     * Get error code's associate message if exist and code itself otherwise
     *
     * @param int $code
     * @param null|string $message
     * @return string
     */
    protected function getMessageFromCode($code, $message)
    {
        if (isset($message))
            return $message;

        $code = strval($code);

        return Arr::get($this->getErrors(), $code, $code);
    }

    public function __construct($code = 0, $message = null, Throwable $previous = null)
    {
        $message = $this->getMessageFromCode($code, $message);
        parent::__construct($message, $code, $previous);
    }
}
