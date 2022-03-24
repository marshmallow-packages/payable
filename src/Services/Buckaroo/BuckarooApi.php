<?php

namespace Marshmallow\Payable\Services\Buckaroo;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class BuckarooApi
{
    public function createPayment(array $payment_data)
    {
        $path = "{$this->getApiHost()}/json/Transaction";

        $response = Http::withHeaders([
            'Authorization' => $this->createAuthorizationHeader(
                path: $path,
                request_data: $payment_data,
            ),
        ])->post($path, $payment_data);

        return $response->json();
    }

    public function refund(string $transactionKey, float $amount)
    {
        $refund_data = [
            'Currency' => 'EUR',
            'AmountCredit' => $amount,
            'Invoice' => 123,
            'OriginalTransactionKey' => $transactionKey,
            'Services' => [
                'ServiceList' => [
                    [
                        'Name' => 'ideal',
                        'Action' => 'Refund',
                    ]
                ],
            ],
        ];

        $path = "{$this->getApiHost()}/json/Transaction";

        $response = Http::withHeaders([
            'Authorization' => $this->createAuthorizationHeader(
                path: $path,
                request_data: $refund_data,
            ),
        ])->post($path, $refund_data);

        return $response->json();

    }

    public function getPaymentStatus(string $transactionKey)
    {
        $path = "{$this->getApiHost()}/json/transaction/status/{$transactionKey}";

        $response = Http::withHeaders([
            'Authorization' => $this->createAuthorizationHeader(
                path: $path,
                method: 'GET',
            ),
        ])->get($path);

        return $response->json();
    }

    protected function createAuthorizationHeader(string $path, string $method = 'POST', array $request_data = [])
    {
        $website_key = config('payable.buckaroo.website_key');
        $nonce = 'nonce_' . Str::random(20);
        $time = now()->timestamp;

        $uri = strtolower(urlencode(
            (string) Str::of($path)->replace(['http://', 'https://'], '')
        ));

        $hmac_data = "{$website_key}{$method}{$uri}{$time}{$nonce}{$this->encodeRequestData($request_data)}";
        $hmac = base64_encode(
            hash_hmac('sha256', $hmac_data, config('payable.buckaroo.secret'), true)
        );

        return "hmac {$website_key}:{$hmac}:{$nonce}:{$time}";
    }

    protected function encodeRequestData(array $request_data = []): string
    {
        if (empty($request_data)) {
            return '';
        }

        return base64_encode(
            md5(
                json_encode($request_data),
                true
            )
        );
    }

    protected function getApiHost()
    {
        return 'https://testcheckout.buckaroo.nl';
    }
}
