<?php

namespace Marshmallow\Payable\Providers;

use Exception;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Marshmallow\Payable\Models\Payment;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;
use Marshmallow\Payable\Http\Responses\PaymentStatusResponse;
use Marshmallow\Payable\Providers\Contracts\PaymentProviderContract;

class PayPal extends Provider implements PaymentProviderContract
{
    protected const SANDBOX_MODE = 'sandbox';

    protected function getClient()
    {
        $clientId = config('payable.paypal.client_id');
        $clientSecret = config('payable.paypal.secret');

        if ($this->isProduction()) {
            $env = new ProductionEnvironment($clientId, $clientSecret);
        } else {
            $env = new SandboxEnvironment($clientId, $clientSecret);
        }

        return new PayPalHttpClient($env);
    }

    protected function isProduction()
    {
        return config('payable.paypal.mode') !== self::SANDBOX_MODE;
    }

    public function createPayment($api_key = null)
    {
        $request = new OrdersCreateRequest();
        $request->prefer('return=representation');
        $request->body = [
            "intent" => "CAPTURE",
            "purchase_units" => [[
                "reference_id" => $this->getPayableIdentifier(),
                "amount" => [
                    "value" => $this->getPayableAmountAsFloat(),
                    "currency_code" => $this->getCurrencyIso4217Code()
                ]
            ]],
            "application_context" => [
                "cancel_url" => $this->redirectUrl(),
                "return_url" => $this->redirectUrl()
            ]
        ];

        return $this->getClient()->execute($request)->result;
    }

    public function getPaymentId()
    {
        return $this->provider_payment_object->id;
    }

    public function getPaymentUrl(): string
    {
        foreach ($this->provider_payment_object->links as $link) {
            if ($link->rel == 'approve') {
                return $link->href;
            }
        }
        return '/?error=No link was generated by PayPal.';
    }

    public function handleReturnNotification(Payment $payment, Request $request): PaymentStatusResponse
    {
        return $this->handleResponse($payment);
    }

    public function handleWebhookNotification(Payment $payment, Request $request): PaymentStatusResponse
    {
        dd(__line__);
        $paymentId = $request->input('id');

        if ($paymentId != $payment->provider_id) {
            abort(403);
        }

        return $this->handleResponse($payment);
    }

    public function convertStatus($status): string
    {
        switch ($status) {
            case 'COMPLETED':
                return Payment::STATUS_PAID;
                break;

            default:
                throw new Exception("Unknown payment status {$status}");
                break;
        }
    }

    public function getPaymentStatus(Payment $payment)
    {
        if ($payment->result_payload) {
            return json_decode($payment->result_payload);
        }
        $request = new OrdersCaptureRequest($payment->provider_id);
        $request->prefer('return=representation');
        $response = $this->getClient()->execute($request);
        $result = $response->result;

        $this->storeResultPayload($payment, json_encode($result));

        return $result;
    }

    public function handleResponse(Payment $payment): PaymentStatusResponse
    {
        $payment = $this->getPaymentStatus($payment);
        $status = $this->convertStatus($payment->status);
        $paid_amount = $this->getPaidAmount($payment);
        return new PaymentStatusResponse($status, $paid_amount);
    }

    public function getPaidAmount($payment)
    {
        $paid_amount = 0;
        foreach ($payment->purchase_units as $unit) {
            $paid_amount += floatval($unit->amount->value);
        }

        return intval(floatval($paid_amount) * 100);
    }

    public function getCanceledAt(Payment $payment): ?Carbon
    {
        if ($payment->isCanceled()) {
            $info = $this->getPaymentInfoFromTheProvider($payment);
            return Carbon::parse($info->update_time)->setTimezone('Europe/Amsterdam');
        }

        return null;
    }

    public function getExpiresAt(Payment $payment): ?Carbon
    {
        if ($payment->isExpired()) {
            $info = $this->getPaymentInfoFromTheProvider($payment);
            return Carbon::parse($info->update_time)->setTimezone('Europe/Amsterdam');
        }

        return null;
    }

    public function getFailedAt(Payment $payment): ?Carbon
    {
        if ($payment->isFailed()) {
            $info = $this->getPaymentInfoFromTheProvider($payment);
            return Carbon::parse($info->update_time)->setTimezone('Europe/Amsterdam');
        }

        return null;
    }

    public function getPaidAt(Payment $payment): ?Carbon
    {
        if ($payment->isPaid()) {
            $info = $this->getPaymentInfoFromTheProvider($payment);
            return Carbon::parse($info->update_time)->setTimezone('Europe/Amsterdam');
        }

        return null;
    }

    public function getConsumerName(Payment $payment): ?string
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        return trim("{$info->payer->name->given_name} {$info->payer->name->surname}");
    }

    public function getConsumerAccount(Payment $payment): ?string
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        return $info->payer->payer_id;
    }

    public function getConsumerBic(Payment $payment): ?string
    {
        return null;
    }

    public function getPaymentTypeName(Payment $payment): ?string
    {
        return null;
    }

    protected function getTestPaymentStatus(): object
    {
        return
            (object) [
                'id' => '1AE76726BL337780U',
                'intent' => 'CAPTURE',
                'status' => 'COMPLETED',
                'purchase_units' => [
                    (object) [
                        'reference_id' => '04667c32-dbe1-4606-812a-f4f6e97e5700',
                        'amount' => (object) [
                            'currency_code' => 'EUR',
                            'value' => '42.95',
                        ],
                        'payee' => (object) [
                            'email_address' => 'sb-28yje6853827@business.example.com',
                            'merchant_id' => '785JB52AFQPUN',
                        ],
                        'shipping' => (object) [
                            'name' => (object) [
                                'full_name' => 'John Doe',
                            ],
                            'address' => (object) [
                                'address_line_1' => '25513540 River N343 W',
                                'admin_area_2' => 'Den Haag',
                                'admin_area_1' => '2585',
                                'postal_code' => '1015 CS',
                                'country_code' => 'NL',
                            ],
                        ],
                        'payments' => (object) [
                            'captures' => [
                                (object) [
                                    'id' => '6LP07175HX217381R',
                                    'status' => 'COMPLETED',
                                    'amount' => (object) [
                                        'currency_code' => 'EUR',
                                        'value' => '42.95',
                                    ],
                                    'final_capture' => true,
                                    'seller_protection' => (object) [
                                        'status' => 'ELIGIBLE',
                                        'dispute_categories' => [
                                            0 => 'ITEM_NOT_RECEIVED',
                                            1 => 'UNAUTHORIZED_TRANSACTION',
                                        ],
                                    ],
                                    'seller_receivable_breakdown' => (object) [
                                        'gross_amount' => (object) [
                                            'currency_code' => 'EUR',
                                            'value' => '42.95',
                                        ],
                                        'paypal_fee' => (object) [
                                            'currency_code' => 'EUR',
                                            'value' => '1.81',
                                        ],
                                        'net_amount' => (object)
                                        [
                                            'currency_code' => 'EUR',
                                            'value' => '41.14',
                                        ],
                                    ],
                                    'links' => [],
                                ],
                            ],
                        ],
                    ],
                ],
                'payer' => (object) [
                    'name' => (object) [
                        'given_name' => 'John',
                        'surname' => 'Doe',
                    ],
                    'email_address' => 'sb-i8al476853828@personal.example.com',
                    'payer_id' => 'YPLW87X2LTQWG',
                    'address' => (object) [
                        'country_code' => 'NL',
                    ],
                ],
                'create_time' => '2021-07-21T15:28:20Z',
                'update_time' => '2021-07-21T15:28:34Z',
                'links' => [
                    (object) [
                        'href' => 'https://api.sandbox.paypal.com/v2/checkout/orders/1AE76726BL337780U',
                        'rel' => 'self',
                        'method' => 'GET',
                    ]
                ],
            ];
    }
}
