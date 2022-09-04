<?php
/**
 * Created by PhpStorm.
 * User: hamed
 * Date: 8/22/22
 * Time: 11:40 AM
 */

namespace Parsisolution\Gateway;

class Curl
{

    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';

    /**
     * Perform a cURL session
     *
     * @param string $url
     * @param array $fields
     * @param bool $resultAsArray
     * @param array $options
     * @param string $method
     * @return array
     */
    public static function execute(
        string $url,
        array $fields,
        bool $resultAsArray = true,
        array $options = [],
        string $method = self::METHOD_POST
    ): array {
        $curl = curl_init();

        curl_setopt_array($curl, $options + [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => (strtoupper($method) == self::METHOD_GET) ? null : $method,
            CURLOPT_POSTFIELDS     => (strtoupper($method) == self::METHOD_GET) ?
                http_build_query($fields) : json_encode($fields),
            CURLOPT_HTTPHEADER     => (strtoupper($method) == self::METHOD_GET) ?
                [
                    'Accept: application/json',
                ] : [
                    'Accept: application/json',
                    'Content-Type: application/json',
                ],
        ]);

        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);

        curl_close($curl);

        $result = json_decode($response, $resultAsArray);

        return [$result, $http_code, $error];
    }

    /**
     * Perform a cURL session
     *
     * @param array $args
     * @return array
     */
    public static function executeArgs(array $args): array
    {
        return self::execute($args[0], $args[1], $args[2] ?? true, $args[3] ?? [], $args[4] ?? self::METHOD_POST);
    }
}