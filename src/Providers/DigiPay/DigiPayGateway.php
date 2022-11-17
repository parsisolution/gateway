<?php

namespace Parsisolution\Gateway\Providers\DigiPay;

class DigiPayGateway
{
    private $username;
    private $password;
    private $client_id;
    private $client_secret;
    private $access_token;
    private $refresh_token;
    private $type;
    private $request_retries = 0;
    private $verify_retries = 0;
    private $liveApi = true;
    private $tokenUpdaterHook;

    public function __construct($settings, $tokenUpdaterHook = null)
    {
        $this->username = $settings['username'];
        $this->password = $settings['password'];
        $this->client_id = $settings['client_id'];
        $this->client_secret = $settings['client_secret'];
        $this->access_token = $settings['access_token'];
        $this->refresh_token = $settings['refresh_token'];
        $this->liveApi = $settings['live_api'] ?? true;
        $this->type = $this->gatewayType(empty($settings['type']) ? '11' : $settings['type']);
        $this->tokenUpdaterHook = $tokenUpdaterHook;

        if (empty($this->access_token)) {
            $this->authenticate();
        }
    }

    private function gatewayType($type): string
    {
        switch ($type) {
            case 'UPG':
                return '11';
            case 'IPG':
                return '0';
            case 'WPG':
                return '2';
        }

        return '11';
    }

    /**
     * @throws DigiPayException()
     */
    private function request($endPoint, $data, $headers = [], $isJSON = false)
    {

        $_headers = [];

        foreach (array_merge([
            'Content-Type' => $isJSON ? 'application/json' : 'application/x-www-form-urlencoded; charset=utf-8',
        ], $headers) as $key => $val) {
            $_headers[] = $key . ': ' . $val;
        }

        $ch = curl_init(($this->liveApi ? 'https://api.mydigipay.com' : 'https://uat.mydigipay.info') . '/digipay/api/' . $endPoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $isJSON ? json_encode($data) : http_build_query($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $_headers);

        $result = curl_exec($ch);

        if (curl_errno($ch) === false) {
            throw new DigiPayException(-1, "[CURL_ERR] " . curl_error($ch));
        }

        $responseCode = curl_getinfo($ch)['http_code'];
        if ($responseCode != 200) {

            $this->request_retries++;
            if ($this->request_retries <= 3) {

                if ($responseCode == 401) {
                    $this->access_token = $this->refresh_token = null;
                    $this->authenticate();
                }

                return $this->request($endPoint, $data, $headers, $isJSON);
            }

            if ($responseCode == 401 && empty($result)) {
                $result = 'Authentication Error. Please check your digipay credentials & try again';
            }

            throw new DigiPayException(
                $responseCode,
                "[REQUEST_ERR][CODE:" . $responseCode . "]" . $result . ((!empty($data['providerId'])) ? "[providerId:" . $data['providerId'] . "]" : '')
            );
        }

        $this->request_retries = 0;

        return json_decode($result, true);

    }

    public function authenticate($refreshToken = true): bool
    {
        $data = ($refreshToken && !empty($this->refresh_token)) ?
            [
                'refresh_token' => $this->refresh_token,
                'grant_type'    => 'refresh_token'
            ]
            :
            [
                'username'   => $this->username,
                'password'   => $this->password,
                'grant_type' => 'password'
            ];

        try {

            $data = $this->request('oauth/token', $data, [
                'Authorization' => 'Basic ' . base64_encode($this->client_id . ':' . $this->client_secret)
            ]);

            $this->access_token = $data['access_token'];
            $this->refresh_token = $data['refresh_token'];

        } catch (\Exception $e) {

            $this->access_token = $this->refresh_token = null;
            return false;
        }

        if (!empty($this->tokenUpdaterHook)) {

            $hook = $this->tokenUpdaterHook;
            $hook($this->access_token, $this->refresh_token);
        }

        return true;
    }

    /**
     * @throws DigiPayException()
     */
    public function createTicket($amount, $providerId, $redirectUrl, $cellNumber = null)
    {
        $userType = empty($cellNumber) ? 2 : 0;

        $data = $this->request('businesses/ticket?type=' . $this->type, [
            'amount'      => $amount,
            'cellNumber'  => $cellNumber,
            'providerId'  => $providerId,
            'redirectUrl' => $redirectUrl,
            'userType'    => $userType,
        ], [
            'Authorization' => 'Bearer ' . $this->access_token
        ], true);

        $status = $data['result']['status'] ?? -1;

        if (!empty($data['result']) && $status == 0) {
            //successful payment
            return [
                'url'    => $data['payUrl'],
                'ticket' => $data['ticket']
            ];
        } else {

            $message = !empty($data['result']['message']) ? $data['result']['message'] : 'unknown error';
            throw new DigiPayException($status, $message . " [providerId: {$providerId}]");
        }
    }

    /**
     * @throws DigiPayException()
     */
    public function verifyTicket($trackingCode)
    {
        if ($this->verify_retries > 3) {
            throw new DigiPayException(-1, "DP_ERR : MAX_RETRIES_EXCEEDED");
        }

        $data = $this->request('purchases/verify/' . $trackingCode, [], [
            'Authorization' => 'Bearer ' . $this->access_token
        ], true);

        $status = $data['result']['status'] ?? -1;

        if ($status == 0) {
            //successful payment
            return $data;

        } else {
            //retry the verification if payment is in pending status

            if ($status == 9011) {

                sleep(10);
                $this->verify_retries++;
                return $this->verifyTicket($trackingCode);

            }

            $message = !empty($data['result']['message']) ? $data['result']['message'] : 'unknown error';

            throw new DigiPayException($status, "DP_ERR : " . $message);

        }
    }
}
