<?php

namespace Marshmallow\Payable\Providers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Eloquent\Model;
use Marshmallow\Payable\Models\Payment;
use Marshmallow\Payable\Models\PaymentType;
use Marshmallow\Payable\Http\Responses\PaymentStatusResponse;

class Provider
{
    protected $payableModel;
    protected $paymentType;
    protected $testPayment;
    protected $payment;
    protected $provider_payment_object;

    public function preparePayment(Model $payableModel, PaymentType $paymentType, $testPayment = null): string
    {
        $this->payableModel = $payableModel;
        $this->paymentType = $paymentType;
        $this->testPayment = $testPayment;

        $this->payment = $payableModel->payments()->create([
            'payment_provider_id' => $paymentType->payment_provider_id,
            'payment_type_id' => $paymentType->id,
            'simple_checkout' => $paymentType->provider->simple_checkout,
            'total_amount' => $payableModel->getTotalAmount(),
            'remaining_amount' => $payableModel->getTotalAmount(),
            'started' => now(),
            'is_test' => $this->isTestPayment($testPayment),
            'start_ip' => request()->ip(),
        ]);

        $this->provider_payment_object = $this->createPayment();
        $this->payment->update([
            'provider_id' => $this->getPaymentId(),
        ]);

        return $this->getPaymentUrl();
    }

    public function handleReturn(Payment $payment, Request $request): RedirectResponse
    {
        $response = $this->handleReturnNotification($payment, $request);

        $this->handleStatusResponse($response, $payment, $request);

        return redirect()->route(
            config('payable.routes.payment_success'),
            [
                'status' => $response->getStatus(),
            ]
        );
    }

    public function handleWebhook(Payment $payment, Request $request): JsonResponse
    {
        $response = $this->handleWebhookNotification($payment, $request);

        $this->handleStatusResponse($response, $payment, $request);

        return response()->json([
            'status' => 200
        ]);
    }

    protected function handleStatusResponse(PaymentStatusResponse $response, Payment $payment, Request $request)
    {
        if ($response->getStatus() != $payment->status) {
            $payment->statusses()->create([
                'status' => $response->getStatus(),
                'return_ip' => $request->ip(),
            ]);

            $payment->update([
                'status_changed_at' => now(),
                'status_change_count' => $payment->status_change_count + 1,
                'status' => $response->getStatus(),
                'return_ip' => $request->ip(),
                'paid_amount' => $response->getPaidAmount(),
            ]);
        }
    }

    protected function getPayableAmount(): int
    {
        return $this->payment->remaining_amount;
    }

    protected function getPayableDescription(): string
    {
        return $this->payableModel->getPayableDescription();
    }

    protected function isTestPayment($testPayment = null): bool
    {
        if (is_bool($testPayment)) {
            return $testPayment;
        }

        return config('payable.test_payments');
    }

    protected function webhookUrl(): ?string
    {
        /**
         * Webhooks can not be executed in local development.
         * Therefor we disable it here.
         */
        if (config('app.env') == 'local') {
            return null;
        }

        if (!$this->payment) {
            throw new Exception('Payment model hasnt been created yet. We can not create a webhook route');
        }
        return route('payable.webhook', [
            'payment' => $this->payment,
        ]);
    }

    protected function redirectUrl(): string
    {
        return route('payable.return', [
            'payment' => $this->payment,
        ]);
    }

    protected function getCurrencyIso4217Code(): string
    {
        return 'EUR';
    }
}
