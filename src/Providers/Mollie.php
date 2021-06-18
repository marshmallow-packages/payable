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

class Mollie extends Provider implements PaymentProviderContract
{
    public function createPayment($api_key = null)
    {
        $api = MollieApi::api();
        if ($api_key) {
            $api->setApiKey($api_key);
        }

        return $api->payments->create([
            'amount' => [
                'currency' => $this->getCurrencyIso4217Code(),
                'value' => $this->formatCentToDecimalString(),
            ],
            'description' => $this->getPayableDescription(),
            'redirectUrl' => $this->redirectUrl(),
            'webhookUrl' => $this->webhookUrl(),
            'locale' => config('payable.locale'),
        ]);
    }

    public function getPaymentId()
    {
        return $this->provider_payment_object->id;
    }

    public function getPaymentUrl(): string
    {
        return $this->provider_payment_object->getCheckoutUrl();
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

    public function convertStatus($status): string
    {
        switch ($status) {
            case 'open':
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
        return MollieApi::api()->payments->get($payment->provider_id);
    }

    public function handleResponse(Payment $payment): PaymentStatusResponse
    {
        $payment = $this->getPaymentStatus($payment);
        $status = $this->convertStatus($payment->status);
        $paid_amount = intval(floatval($payment->amount->value) * 100);
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

    public function getConsumerName(Payment $payment): ?string
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        return $info->details->consumerName;
    }

    public function getConsumerAccount(Payment $payment): ?string
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        return $info->details->consumerAccount;
    }

    public function getConsumerBic(Payment $payment): ?string
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        return $info->details->consumerBic;
    }

    public function getPaymentTypeName(Payment $payment): ?string
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        return $info->method;
    }
}
