<?php

namespace Marshmallow\Payable\Providers;

use Exception;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Stripe\Checkout\Session;
use Stripe\Stripe as StripApi;
use Marshmallow\Payable\Models\Payment;
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

        return Session::create([
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
            'customer_email' => $this->payableModel->getCustomerEmail(),
        ]);
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


        switch ($event->type) {
            case 'payment_intent.succeeded':
            case 'payment_intent.requires_action':
            case 'payment_intent.processing':
            case 'payment_intent.payment_failed':
            case 'payment_intent.canceled':
            case 'payment_intent.amount_capturable_updated':
                return $this->convertWebhookDataToPaymentModel($request);
                break;

            default:
                throw new Exception("Received unknown event type {$event->type}");
        }
    }

    protected function convertWebhookDataToPaymentModel(Request $request): Payment
    {
        $stripe = $this->getStripeClient();
        $sessions = $stripe->checkout->sessions->all([
            'payment_intent' => $request->data['object']['id'],
            'limit' => 1,
        ]);

        $session = $sessions->first();
        if (!$session) {
            throw new Exception("Stripe session could not be found");
        }

        $payment = config('payable.models.payment')::where('provider_id', $session->id)->first();
        if (!$payment) {
            throw new Exception("Payment could not be found with the provided stripe session id.");
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

            case 'paid':
                return Payment::STATUS_PAID;
                break;

            case 'unpaid':
                return Payment::STATUS_FAILED;
                break;

            default:
                throw new Exception("Unknown payment status {$status}");
                break;
        }
    }

    public function getPaymentStatus(Payment $payment)
    {
        $stripe = $this->getStripeClient();

        return $stripe->checkout->sessions->retrieve(
            $payment->provider_id,
            []
        );
    }

    public function handleResponse(Payment $payment): PaymentStatusResponse
    {
        $payment = $this->getPaymentStatus($payment);
        $status = $this->convertStatus($payment->payment_status);
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
        $payment = $this->getPaymentStatus($payment);

        $stripe = $this->getStripeClient();

        $payment_intent = $stripe->paymentIntents->retrieve(
            $payment->payment_intent,
            []
        );

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
