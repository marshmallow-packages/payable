<?php

namespace Marshmallow\Payable\Providers;

use Exception;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Marshmallow\Payable\Models\Payment;
use Marshmallow\Payable\Http\Responses\PaymentStatusResponse;
use Marshmallow\Payable\Providers\Contracts\PaymentProviderContract;
use Marshmallow\Payable\Services\Ippies\Facades\Ippies as IppiesFacade;

class Ippies extends Provider implements PaymentProviderContract
{
    public function createPayment()
    {
        $payment_data = [
            'pay_orderid' => $this->getPayOrderId($this->payableModel),
            'pay_amount' => $this->getPayableAmount(),
            'return_normal' => $this->redirectUrl(),
            'return_true' => $this->redirectUrl(),
            'return_false' => $this->redirectUrl(),
        ];

        return IppiesFacade::createPayment($payment_data);
    }

    protected function getPayOrderId(Model $payableModel)
    {
        return (string) Str::of($payableModel->getPayableDescription())->limit(50)->slug();
    }

    public function getPaymentId()
    {
        return $this->provider_payment_object->getPaymentOrderId();
    }

    public function getPaymentUrl(): string
    {
        return $this->provider_payment_object->getPaymentUrl();
    }

    public function handleReturnNotification(Payment $payment, Request $request): PaymentStatusResponse
    {
        return $this->handleResponse($payment);
    }

    public function handleWebhookNotification(Payment $payment, Request $request): PaymentStatusResponse
    {
        return $this->handleResponse($payment);
    }

    public function convertStatus($status): string
    {
        switch ($status) {

            case 'success':
                return Payment::STATUS_PAID;
                break;

            case 'failed':
                return Payment::STATUS_FAILED;
                break;

            default:
                throw new Exception("Unknown payment status {$status}");
                break;
        }
    }

    public function getPaymentStatus(Payment $payment)
    {
        $status = IppiesFacade::getPaymentStatus(
            $this->getPayOrderId($payment->payable),
            $payment->total_amount
        );

        $this->storeResultPayloadText($payment, $status->getStatusResponseResult());

        return $status->getStatus();
    }

    public function handleResponse(Payment $payment): PaymentStatusResponse
    {
        $payment_status = $this->getPaymentStatus($payment);
        $status = $this->convertStatus($payment_status->type);
        $paid_amount = ($status == Payment::STATUS_PAID) ? $payment->total_amount : 0;

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

    public function getConsumerName(Payment $payment): ?string
    {
        return null;
    }

    public function getConsumerAccount(Payment $payment): ?string
    {
        return null;
    }

    public function getConsumerBic(Payment $payment): ?string
    {
        return null;
    }

    public function getPaymentTypeName(Payment $payment): ?string
    {
        return 'ippies';
    }
}
