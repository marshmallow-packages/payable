<?php

namespace Marshmallow\Payable\Providers;

use Exception;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Marshmallow\Payable\Models\Payment;
use Marshmallow\Payable\Facades\Payable;
use Mollie\Laravel\Facades\Mollie as MollieApi;
use Marshmallow\Payable\Http\Responses\PaymentStatusResponse;
use Marshmallow\Payable\Providers\Contracts\PaymentProviderContract;

class Stripe extends Provider implements PaymentProviderContract
{
    public $stripe;

    public function __construct()
    {
        $api_secret = config('cashier.secret');
        $this->stripe = new \Stripe\StripeClient($api_secret);
    }

    public function createPayment($api_key = null)
    {
        //https://stripe.com/docs/api/checkout/sessions/create?lang=php
        return $this->stripe->checkout->sessions->create([
            'success_url' => $this->redirectUrl(),
            'cancel_url' => $this->redirectUrl(),  // TODO
            'payment_method_types' => ['ideal'],
            'locale' => 'auto',
            'line_items' => [
                [
                    'amount' => $this->getPayableAmount(),
                    'quantity' => $this->getPayableAmount(), // TODO
                    'currency' => $this->getCurrencyIso4217Code(),
                    'name' => $this->getPayableDescription(), // TODO
                    'description' => $this->getPayableDescription(),
                ],
            ],
            'mode' => 'payment',
        ]);
    }

    public function createStripeCustomer()
    {
        $customer = $this->stripe->customers->create([
            'description' => $this->getPayableDescription(),
        ]);

        return $customer->id;
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

    public function handleWebhookNotification(Payment $payment, Request $request): PaymentStatusResponse
    {
        $paymentId = $request->input('id');

        if ($paymentId != $payment->provider_id) {
            abort(403);
        }

        return $this->handleResponse($payment);
    }

    // TODO
    public function convertStatus($status): string
    {
        switch ($status) {
            case 'unpaid':
                return Payment::STATUS_OPEN;
                break;

            case 'paid':
                return Payment::STATUS_PAID;
                break;

            case 'failed':
                return Payment::STATUS_FAILED;
                break;

            case 'canceled':
                return Payment::STATUS_CANCELED;
                break;

            case 'expired':
                return Payment::STATUS_EXPIRED;
                break;

            default:
                throw new Exception("Unknown payment status {$status}");
                break;
        }
    }

    public function getPaymentStatus(Payment $payment)
    {
        return $this->stripe->checkout->sessions->retrieve(
            $payment->provider_id,
            []
        );
    }

    public function handleResponse(Payment $payment): PaymentStatusResponse
    {
        $payment = $this->getPaymentStatus($payment);
        $status = $this->convertStatus($payment->payment_status);
        $paid_amount = intval(floatval($payment->amount_total) * 100);
        return new PaymentStatusResponse($status, $paid_amount);
    }

    public function formatCentToDecimalString(): string
    {
        /**
         * Mollie says;
         * You must send the correct number of decimals, thus we enforce the use of strings
         */
        $payable_amount = $this->getPayableAmount();
        return number_format($payable_amount / 100, 2, '.', '');
    }

    public function getCanceledAt(Payment $payment): ?Carbon
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        return Carbon::parse($info->canceledAt);
    }

    public function getExpiresAt(Payment $payment): ?Carbon
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        return Carbon::parse($info->expiresAt);
    }

    public function getFailedAt(Payment $payment): ?Carbon
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        return Carbon::parse($info->failedAt);
    }

    public function getPaidAt(Payment $payment): ?Carbon
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        return Carbon::parse($info->paidAt);
    }

    protected function getPaymentDetail(Payment $payment, string $column): ?string
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        if (!isset($info->details)) {
            return null;
        }
        if (!isset($info->details->{$column})) {
            return null;
        }

        return $info->details->{$column};
    }

    public function getConsumerName(Payment $payment): ?string
    {
        return $this->getPaymentDetail($payment, 'consumerName');
    }

    public function getConsumerAccount(Payment $payment): ?string
    {
        return $this->getPaymentDetail($payment, 'consumerAccount');
    }

    public function getConsumerBic(Payment $payment): ?string
    {
        return $this->getPaymentDetail($payment, 'consumerBic');
    }

    public function getPaymentTypeName(Payment $payment): ?string
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        return $info->method;
    }
}
