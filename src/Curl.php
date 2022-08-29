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
        string $method = 'POST'
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
            CURLOPT_CUSTOMREQUEST  => (strtoupper($method) == 'GET') ? null : $method,
            CURLOPT_POSTFIELDS     => (strtoupper($method) == 'GET') ? http_build_query($fields) : json_encode($fields),
            CURLOPT_HTTPHEADER     => (strtoupper($method) == 'GET') ?
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
}