<?php

namespace Marshmallow\Payable\Providers;

use Exception;
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

    protected function handleResponse(Payment $payment)
    {
        $payment = $this->getPaymentStatus($payment);
        $status = $this->convertStatus($payment->status);
        $paid_amount = intval(floatval($payment->amount->value) * 100);
        return new PaymentStatusResponse($status, $paid_amount);
    }

    protected function formatCentToDecimalString(): string
    {
        /**
         * Mollie says;
         * You must send the correct number of decimals, thus we enforce the use of strings
         */
        $payable_amount = $this->getPayableAmount();
        return number_format($payable_amount / 100, 2, '.', '');
    }

    protected function getCanceledAt(Payment $payment)
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        return $info->canceledAt;
    }

    protected function getExpiresAt(Payment $payment)
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        return $info->expiresAt;
    }

    protected function getFailedAt(Payment $payment)
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        return $info->failedAt;
    }

    protected function getPaidAt(Payment $payment)
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        return $info->paidAt;
    }

    protected function getConsumerName(Payment $payment)
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        return $info->details->consumerName;
    }

    protected function getConsumerAccount(Payment $payment)
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        return $info->details->consumerAccount;
    }

    protected function getConsumerBic(Payment $payment)
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        return $info->details->consumerBic;
    }
}
