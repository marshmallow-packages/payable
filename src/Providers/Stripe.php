<?php

namespace Marshmallow\Payable\Providers;

use Exception;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Stripe\Checkout\Session;
use Stripe\Stripe as StripApi;
use Marshmallow\Payable\Models\Payment;
use Marshmallow\Payable\Events\ExternalCustomerModified;
use Marshmallow\Payable\Http\Responses\PaymentStatusResponse;
use Marshmallow\Payable\Providers\Contracts\PaymentProviderContract;

class Stripe extends Provider implements PaymentProviderContract
{
    protected function getStripeClient()
    {
        return new \Stripe\StripeClient(
            config('payable.stripe.secret')
        );
    }

    public function createPayment($api_key = null)
    {
        $api_key = ($api_key) ? $api_key : config('payable.stripe.secret');
        StripApi::setApiKey($api_key);

        $session_data = [
            'payment_method_types' => $this->paymentType->vendor_type_options,
            'line_items' => [[
                'data' => [],
                'price_data' => [
                    'currency' => $this->getCurrencyIso4217Code(),
                    'product_data' => [
                        'name' => $this->getPayableDescription(),
                    ],
                    'unit_amount' => $this->getPayableAmount(),
                ],
                'quantity' => 1,
            ]],
            'locale' => config('payable.locale_iso_639'),
            'mode' => 'payment',
            'success_url' => $this->redirectUrl(),
            'cancel_url' => $this->cancelUrl(),
        ];

        if ($this->payableModel?->id) {
            $session_data['client_reference_id'] = $this->payableModel?->id;
        }

        $session_data['metadata'] = [
            'payable_type' => $this->payableModel?->getMorphClass(),
            'payable_id' => $this->payableModel?->id,
            'customer_id' => $this->payableModel?->getCustomerId() ?? '',
        ];

        if ($this->payableModel?->customer?->payable_external_id) {
            $session_data['customer'] = $this->payableModel->payable_external_id;
            $session_data['customer_update'] = [
                'name' => 'auto',
                'address' => 'auto',
            ];
            $session_data['payment_intent_data']['setup_future_usage'] = 'off_session';
        } elseif ($this->payableModel?->getCustomerEmail()) {
            $session_data['customer_email'] = $this->payableModel->getCustomerEmail();
        }

        return Session::create($session_data);
    }

    public function getPaymentId()
    {
        return $this->provider_payment_object->id;
    }

    public function getPaymentUrl(): string
    {
        return $this->provider_payment_object->url;
    }

    public function handleReturnNotification(Payment $payment, Request $request): PaymentStatusResponse
    {
        return $this->handleResponse($payment);
    }

    public function guard(Request $request): Payment
    {
        if (!$request->hasHeader('stripe-signature')) {
            throw new Exception("Invalid header", 1);
        }

        $event = \Stripe\Webhook::constructEvent(
            @file_get_contents('php://input'),
            $request->header('stripe-signature'),
            config('payable.stripe.webhook')
        );

        if (in_array($event->type, config('payable.stripe.event_types'))) {
            return $this->convertWebhookDataToPaymentModel($request);
        } elseif (in_array($event->type, config('payable.stripe.customer_event_types'))) {
            $payable_external_id = Arr::get($request->data, 'object.id');
            $payload_data = Arr::get($request->data, 'object');
            event(new ExternalCustomerModified($payable_external_id, $payload_data));
            abort(200);
        }

        abort(404, "Received unknown event type {$event->type}");
    }

    protected function convertWebhookDataToPaymentModel(Request $request): Payment
    {
        $stripe = $this->getStripeClient();

        $payment_intend_id = Arr::get($request->data, 'object.id');

        $sessions = $stripe->checkout->sessions->all([
            'payment_intent' => $payment_intend_id,
            'limit' => 1,
        ]);

        $session = $sessions->first();

        if (!$session) {
            abort(404, "Stripe session could not be found");
        }

        $payment = config('payable.models.payment')::where('provider_id', $session->id)->first();
        if (!$payment) {
            abort(404, "Payment could not be found with the provided stripe session id.");
        }
        return $payment;
    }

    public function handleWebhookNotification(Payment $payment, Request $request): PaymentStatusResponse
    {
        return $this->handleResponse($payment);
    }

    public function convertStatus($status): string
    {
        switch ($status) {

            case 'succeeded':
                return Payment::STATUS_PAID;
                break;

            case 'requires_action':
            case 'requires_confirmation':
            case 'requires_capture':
                return Payment::STATUS_PENDING;
                break;

            case 'processing':
                return Payment::STATUS_OPEN;
                break;

            case 'requires_payment_method':
                return Payment::STATUS_FAILED;
                break;

            case 'canceled':
                return Payment::STATUS_CANCELED;

            default:
                throw new Exception("Unknown payment status {$status}");
                break;
        }
    }

    public function getPaymentStatus(Payment $payment)
    {
        $stripe = $this->getStripeClient();

        $payment_session = $stripe->checkout->sessions->retrieve(
            $payment->provider_id,
            []
        );

        $customer_id = $payment->payable?->getCustomerId();

        $payment_intent = $stripe->paymentIntents->update(
            $payment_session->payment_intent,
            [
                'metadata' => [
                    'payable_type' => $payment->payable_type,
                    'payable_id' => $payment->payable_id,
                    'customer_id' => $customer_id ?? '',
                ]
            ]
        );

        if ($customer_id && $payment_intent->customer) {
            $stripe->customers->update(
                $payment_intent->customer,
                [
                    'metadata' => [
                        'customer_id' => $customer_id,
                    ]
                ]
            );
        }

        return $payment_intent;
    }

    public function handleResponse(Payment $payment): PaymentStatusResponse
    {
        $payment_intent = $this->getPaymentStatus($payment);

        $status = $this->convertStatus($payment_intent->status);
        $paid_amount = ($status == Payment::STATUS_PAID) ? $payment->amount_total : 0;
        $paid_amount = intval(floatval($paid_amount));

        return new PaymentStatusResponse($status, $paid_amount);
    }

    public function getCanceledAt(Payment $payment): ?Carbon
    {
        return now();
    }

    public function getExpiresAt(Payment $payment): ?Carbon
    {
        return now();
    }

    public function getFailedAt(Payment $payment): ?Carbon
    {
        return now();
    }

    public function getPaidAt(Payment $payment): ?Carbon
    {
        return now();
    }

    protected function getPaymentMethod(Payment $payment): ?\Stripe\PaymentMethod
    {
        $payment_intent = $this->getPaymentStatus($payment);

        $stripe = $this->getStripeClient();

        if (!$payment_intent->payment_method) {
            return null;
        }

        return $stripe->paymentMethods->retrieve(
            $payment_intent->payment_method,
            []
        );
    }

    public function getConsumerName(Payment $payment): ?string
    {
        $payment_method = $this->getPaymentMethod($payment);
        if (!$payment_method || !isset($payment_method->billing_details)) {
            return null;
        }

        return $payment_method->billing_details->name;
    }

    public function getConsumerAccount(Payment $payment): ?string
    {
        return null;
    }

    public function getConsumerBic(Payment $payment): ?string
    {
        $payment_method = $this->getPaymentMethod($payment);
        if (!$payment_method) {
            return null;
        }

        return match ($payment_method->type) {
            'ideal' => $payment_method->ideal->bic,
            default => null,
        };
    }

    public function getPaymentTypeName(Payment $payment): ?string
    {
        $payment_method = $this->getPaymentMethod($payment);
        return ($payment_method) ? $payment_method->type : null;
    }
}
