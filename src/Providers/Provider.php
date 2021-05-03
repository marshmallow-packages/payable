<?php

namespace Marshmallow\Payable\Providers;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Eloquent\Model;
use Marshmallow\Payable\Models\Payment;
use Marshmallow\Payable\Facades\Payable;
use Marshmallow\Payable\Models\PaymentType;
use Marshmallow\Payable\Http\Responses\PaymentStatusResponse;

class Provider
{
    protected $payableModel;
    protected $paymentType;
    protected $testPayment;
    protected $payment;
    protected $provider_payment_object;
    protected $payment_info_result;

    public function preparePayment(Model $payableModel, PaymentType $paymentType, $testPayment = null, $api_key = null): string
    {
        $this->payableModel = $payableModel;
        $this->paymentType = $paymentType;
        $this->testPayment = $testPayment;

        $this->payment = $payableModel->payments()->create([
            'payment_provider_id' => $paymentType->payment_provider_id,
            'payment_type_id' => $paymentType->id,
            'simple_checkout' => $paymentType->simple_checkout,
            'total_amount' => $payableModel->getTotalAmount(),
            'remaining_amount' => $payableModel->getTotalAmount(),
            'started' => now(),
            'is_test' => $this->isTestPayment($testPayment),
            'start_ip' => request()->ip(),
        ]);

        $this->provider_payment_object = $this->createPayment($api_key);
        $this->payment->update([
            'provider_id' => $this->getPaymentId(),
        ]);

        return $this->getPaymentUrl();
    }

    public function handleReturn(Payment $payment, Request $request): RedirectResponse
    {
        $response = $this->handleReturnNotification($payment, $request);

        $this->handleStatusResponse($response, $payment, $request);

        if ($payment->isOpen()) {
            $route_name = config('payable.routes.payment_open');
        } elseif ($payment->isPaid()) {
            $route_name = config('payable.routes.payment_paid');
        } elseif ($payment->isFailed()) {
            $route_name = config('payable.routes.payment_failed');
        } elseif ($payment->isCanceled()) {
            $route_name = config('payable.routes.payment_canceled');
        } elseif ($payment->isExpired()) {
            $route_name = config('payable.routes.payment_expired');
        } else {
            $route_name = config('payable.routes.payment_unknown');
        }

        return redirect()->route(
            $route_name,
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

        /**
         * Store extra payment information
         */
        $payment->update([
            'canceled_at' => $this->getCanceledAt($payment),
            'expires_at' => $this->getExpiresAt($payment),
            'failed_at' => $this->getFailedAt($payment),
            'paid_at' => $this->getPaidAt($payment),
            'consumer_name' => $this->getConsumerName($payment),
            'consumer_account' => $this->getConsumerAccount($payment),
            'consumer_bic' => $this->getConsumerBic($payment),
        ]);
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
        if (config('app.env') == 'local' && !env('SHARED_WITH_EXPOSE')) {
            return null;
        }

        if (!$this->payment) {
            throw new Exception('Payment model hasnt been created yet. We can not create a webhook route');
        }

        $webhook_path = route('payable.webhook', [
            'payment' => $this->payment,
        ]);

        if (env('SHARED_WITH_EXPOSE')) {
            $webhook_path = Str::replaceFirst(config('app.url'), env('SHARED_WITH_EXPOSE'), $webhook_path);
        }

        return $webhook_path;
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

    protected function getPaymentInfoFromTheProvider(Payment $payment)
    {
        /**
         * If the info is already available, we can just return it again.
         */
        if ($this->payment_info_result) {
            return $this->payment_info_result;
        }

        /**
         * Get the raw status of the payment from the payment provider.
         */
        $provider = Payable::getProvider();

        /**
         * Store the result so we don't have to get this infromation
         * mulitple times.
         */
        $this->payment_info_result = $provider->getPaymentStatus($payment);

        /**
         * Return the raw payment data.
         */
        return $this->payment_info_result;
    }

    protected function getCanceledAt(Payment $payment)
    {
        return null;
    }

    protected function getExpiresAt(Payment $payment)
    {
        return null;
    }

    protected function getFailedAt(Payment $payment)
    {
        return null;
    }

    protected function getPaidAt(Payment $payment)
    {
        return null;
    }

    protected function getConsumerName(Payment $payment)
    {
        return null;
    }

    protected function getConsumerAccount(Payment $payment)
    {
        return null;
    }

    protected function getConsumerBic(Payment $payment)
    {
        return null;
    }
}
