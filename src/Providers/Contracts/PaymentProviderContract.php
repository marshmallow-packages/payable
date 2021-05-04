<?php

namespace Marshmallow\Payable\Providers\Contracts;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Marshmallow\Payable\Models\Payment;
use Marshmallow\Payable\Models\PaymentType;
use Marshmallow\Payable\Http\Responses\PaymentStatusResponse;

interface PaymentProviderContract
{
    /**
     * Get the payment URL from the $provider_payment_object variable
     */
    public function getPaymentUrl(): string;

    /**
     * Get the payment id from the provider out of $provider_payment_object
     */
    public function getPaymentId();

    /**
     * Create a payment and store it in $provider_payment_object
     */
    public function createPayment();

    /**
     * Convert the status of the provider to a status that we understand.
     */
    public function convertStatus($status): string;

    public function preparePayment(Model $payableModel, PaymentType $paymentType): string;
    public function handleReturnNotification(Payment $payment, Request $request): PaymentStatusResponse;
    public function handleWebhookNotification(Payment $payment, Request $request): PaymentStatusResponse;

    public function getPaymentStatus(Payment $payment);
    public function handleResponse(Payment $payment): PaymentStatusResponse;
    public function getCanceledAt(Payment $payment): ?Carbon;
    public function getExpiresAt(Payment $payment): ?Carbon;
    public function getFailedAt(Payment $payment): ?Carbon;
    public function getPaidAt(Payment $payment): ?Carbon;
    public function getConsumerName(Payment $payment): ?string;
    public function getConsumerAccount(Payment $payment): ?string;
    public function getConsumerBic(Payment $payment): ?string;
    public function getPaymentTypeName(Payment $payment): ?string;
}
