<?php

namespace Parsisolution\Gateway\Providers\Sadad;

use Parsisolution\Gateway\Exceptions\TransactionException;

class SadadException extends TransactionException
{

    protected function getMessageFromCode($code, $message)
    {
        return $this->getSadadMessage($code, $message)['fa'];
    }

    /**
     * returns an associative array of `code` => `message`
     *
     * @return array
     */
    protected function getErrors()
    {
        return [
            SadadResult::UNKNOWN_CODE => $this->getSadadMessage(
                SadadResult::UNKNOWN_CODE,
                SadadResult::UNKNOWN_MESSAGE
            )['fa'],
        ];
    }

    /**
     * Get Error response from Sadad Responses
     *
     * @param int $code
     * @param string $message
     *
     * @return array
     */
    private function getSadadMessage($code, $message)
    {
        $result = SadadResult::codeResponse($code, $message);
        if (! $result) {
            $result = array(
                'code'    => SadadResult::UNKNOWN_CODE,
                'message' => SadadResult::UNKNOWN_MESSAGE,
                'fa'      => 'خطای ناشناخته',
                'en'      => 'Unknown Error',
                'retry'   => false,
            );
        }

        return $result;
    }
}
