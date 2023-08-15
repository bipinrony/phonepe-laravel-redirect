<?php

namespace App\Services;

use Exception;

class PhonePe
{
    public $mercuryBaseUrl;
    public $salt;
    public $callback_url;
    public $redirect_url;
    public $merchantKey;
    public $merchantId;
    public $storeId;
    public $expiresIn;
    public $terminalId;
    public $saltIndex;

    public function __construct()
    {
        $this->mercuryBaseUrl = 'https://api-preprod.phonepe.com/apis/pg-sandbox';
        // $this->mercuryBaseUrl = 'https://api.phonepe.com/apis/hermes'; // PROD

        $this->callback_url = route('phonepe.payment.callback');
        $this->redirect_url = route('phonepe.payment.callback');
        // $this->merchantKey = 'c022af70-e747-40b3-a9ec-8ce1aabdfce7';
        // $this->merchantId = 'DOUBLEONLINE';
        $this->merchantKey = '099eb0cd-02cf-4e2a-8aca-3e6c6aff0399';
        $this->merchantId = 'PGTESTPAYUAT';
        $this->expiresIn = '1800';
        $this->saltIndex = "1";
    }

    public function postRequest($url, $body, $headers)
    {
        $response = [];
        try {
            $client = new \GuzzleHttp\Client();
            $phonePeresponse = $client->request('POST', $url, [
                'body' => $body,
                'headers' => $headers,
            ]);

            $response['flag'] = true;
            $response['response'] = $phonePeresponse->getBody()->getContents();
        } catch (Exception $e) {
            $response['flag'] = false;
            $response['message'] = $e->getMessage();
        }
        \Log::info('[PhonePe@postRequest]: ', $response);
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
            "redirectUrl" => $this->redirect_url,
            "redirectMode" => "POST",
            'callbackUrl' => $this->callback_url,
            'paymentInstrument' => [
                "type" => "PAY_PAGE"
            ],
        ]);
        $base64Json = base64_encode(json_encode($payload));
        $requestPayload  = json_encode(array("request" => $base64Json));
        // $endpoint = "/v3/qr/init";
        $endpoint = "/pg/v1/pay";

        $checksum = $this->checkSumGenerate($base64Json, $endpoint, $this->merchantKey, $this->saltIndex);
        $txnURL = $this->mercuryBaseUrl . $endpoint;

        $response = $this->postRequest(
            $txnURL,
            $requestPayload,
            [
                'Content-Type' => 'application/json',
                'X-VERIFY' => $checksum,
                // 'X-CALLBACK-URL' => $this->x_callback_url,
                'accept' => 'application/json',
            ],
        );
        if ($response['flag']) {
            $responseArr = json_decode($response['response']);
            $response['redirectUrl'] = $responseArr->data->instrumentResponse->redirectInfo->url;
            $response['message'] = $responseArr->message;
        }
        return $response;
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