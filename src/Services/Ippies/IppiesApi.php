<?php

namespace Marshmallow\Payable\Services\Ippies;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class IppiesApi
{
    protected $pay_orderid;
    protected $pay_amount;
    protected $payment_attributes;

    protected $status;
    protected $status_response_result;

    public function createPayment(array $payment_attributes): self
    {
        $this->setParameters($payment_attributes);
        return $this;
    }

    public function createTestPayment(array $payment_attributes): self
    {
        return $this->createPayment(array_merge($payment_attributes, [
            'test' => 1,
        ]));
    }

    public function getPaymentStatus($order_id, $amount)
    {
        $rest_client_id = config('payable.ippies.rest_client_id');
        $rest_client_secret = config('payable.ippies.rest_client_secret');

        if ($rest_client_id && $rest_client_secret) {

            $response = Http::withoutVerifying()->post("https://rest2.ippies.nl/grant", [
                "grant_type" => "client_credentials",
                "client_id" => $rest_client_id,
                "client_secret" => $rest_client_secret,
            ]);

            $token = Arr::get($response->json(), "access_token");

            $shop_id = $this->getShopId();
            $hash = md5(
                $shop_id . $order_id . $amount . $rest_client_secret
            );

            $res = Http::withToken($token)->get("https://rest2.ippies.nl/payment/status", [
                "shop_id" => $shop_id,
                "order_id" => $order_id,
                "paid_amount" => $amount,
                "hash" => $hash
            ]);

            $this->status = (object) [
                'type' => Arr::get($res, 'type'),
            ];

            return $this;
        }
        $status_api_key = config('payable.ippies.status_api_key');
        $hash = sha1($this->getShopId() . md5($status_api_key) . $order_id . $amount);
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><request><key>{$status_api_key}</key><service>CheckPayment</service><params><orderid>{$order_id}</orderid><amount>{$amount}</amount><hash>{$hash}</hash></params></request>";

        $response = Http::asForm()->post(config('payable.ippies.status_api'), [
            'xml' => $xml,
        ]);

        $this->status_response_result = $response->getBody();

        $xml = simplexml_load_string($response->getBody(), 'SimpleXMLElement', LIBXML_NOCDATA);

        $this->status = (object) [
            'type' => (string) $xml->type,
        ];

        return $this;
    }

    public function getStatusResponseResult()
    {
        return $this->status_response_result;
    }

    public function getStatus()
    {
        return $this->status;
    }

    protected function getShopId(): int
    {
        return intval(config('payable.ippies.shop_id'));
    }

    protected function createHash(): string
    {
        return sha1($this->getShopId() . md5(config('payable.ippies.key')) . $this->pay_orderid . $this->pay_amount);
    }

    protected function setParameters(array $payment_attributes): void
    {
        $this->pay_orderid = $payment_attributes['pay_orderid'];
        $this->pay_amount = $payment_attributes['pay_amount'];
        $this->payment_attributes = $payment_attributes;
    }

    protected function getApiPath(): string
    {
        return config('payable.ippies.api');
    }

    public function getPaymentUrl(): string
    {
        $payment_attributes = $this->getPaymentAttributes();
        $payment_attributes = array_merge($payment_attributes, [
            'pay_shopid' => $this->getShopId(),
            'pay_hash' => $this->createHash(),
        ]);

        $api_path = $this->getApiPath();
        $query = http_build_query($payment_attributes);

        return "{$api_path}?{$query}";
    }

    public function getPaymentAttributes(): array
    {
        return $this->payment_attributes;
    }

    public function getPaymentOrderId(): string
    {
        return $this->pay_orderid;
    }
}
