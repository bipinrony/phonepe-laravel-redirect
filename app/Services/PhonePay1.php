<?php

namespace App\Services;

use Exception;

class PhonePay
{
    public $mercuryBaseUrl;
    public $salt;
    public $x_callback_url;
    public $merchantKey;
    public $merchantId;
    public $storeId;
    public $expiresIn;
    public $terminalId;
    public $saltIndex;

    public function __construct()
    {
        $this->mercuryBaseUrl = 'https://mercury-uat.phonepe.com/enterprise-sandbox';
        $this->x_callback_url = 'http://localhost:8000/test-pp';
        $this->merchantKey = '099eb0cd-02cf-4e2a-8aca-3e6c6aff0399';
        $this->merchantId = 'MERCHANTUAT';
        $this->storeId = '234555';
        // $this->merchantKey = 'c022af70-e747-40b3-a9ec-8ce1aabdfce7';
        // $this->merchantId = 'DOUBLEONLINE';
        // $this->storeId = 'Doublespeed1';
        $this->expiresIn = '1800';
        $this->terminalId = '894237';
        $this->saltIndex = "1";
    }

    public function postRequest($url, $body, $headers)
    {
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $url, [
                'body' => $body,
                'headers' => $headers,
            ]);

            return $response->getBody()->getContents();
        } catch (Exception $e) {
            dd($e->getMessage());
        }
    }

    /**
     * @desc Helper function to send a get request
     * @param $url
     * @param $headers
     * @return mixed
     */
    public function getRequest($url, $headers)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    public function checkSumGenerate($base64_encoded_payload, $endpoint, $salt, $salt_index)
    {
        $string_to_be_hashed = $base64_encoded_payload . $endpoint .  $salt;
        $sha256_hash = hash('sha256', $string_to_be_hashed);
        $hashed_string = $sha256_hash . "###" . $salt_index;
        return $hashed_string;
    }

    public function generateQrCode($requestData)
    {
        $payload = array_merge($requestData, [
            'merchantId' => $this->merchantId,
            'expiresIn' => $this->expiresIn,
            'storeId' => $this->storeId,
            'terminalId' => $this->terminalId,
            'expiresIn' => $this->expiresIn
        ]);
        $base64Json = base64_encode(json_encode($payload));
        $requestPayload  = json_encode(array("request" => $base64Json));
        $endpoint = "/v3/qr/init";
        $checksum = $this->checkSumGenerate($base64Json, $endpoint, $this->merchantKey, $this->saltIndex);
        $txnURL = $this->mercuryBaseUrl . $endpoint;

        $response = $this->postRequest(
            $txnURL,
            $requestPayload,
            [
                'Content-Type' => 'application/json',
                'X-VERIFY' => $checksum,
                'X-CALLBACK-URL' => $this->x_callback_url,
                'accept' => 'application/json',
                'X-CALL-MODE' => "HTTP"
            ],
        );
        if ($response) {
            $response = json_decode($response);
            if ($response->success) {
                // dd($response);
                return $response->data->qrString;
            }
        }
        return false;
    }

    public function transactionStatus($request, $phonePeClientConfig)
    {
        $url = $phonePeClientConfig->mercuryBaseUrl . '/v1/transaction/' . $request->merchantId . '/' . $request->transactionId . '/status';
        $args = array($request->merchantId, $request->transactionId, $request->header->salt->key, $request->header->salt->index);
        $headers = 'Content-type:application/json ' . 'X-VERIFY:' . $this->checkSumGenerate($args);
        $response = json_decode($this->getRequest($url, $headers));
        return $response;
    }
}