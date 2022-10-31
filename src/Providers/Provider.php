<?php

namespace Marshmallow\Payable\Providers;

use Exception;
use Carbon\Carbon;
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
    protected $extra_payment_data_callback;
    protected $is_recurring = false;

    public function preparePayment(
        Model $payableModel,
        PaymentType $paymentType,
        $testPayment = null,
        $api_key = null,
        callable $extraPaymentDataCallback = null,
        callable $extraPaymentModifier = null
    ): string {
        $this->payableModel = $payableModel;
        $this->paymentType = $paymentType;
        $this->testPayment = $testPayment;
        $this->extraPaymentDataCallback = $extraPaymentDataCallback;

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

        $method = ($this->is_recurring) ? 'createRecurringPayment' : 'createPayment';
        $this->provider_payment_object = $this->{$method}($api_key);
        $this->payment->update([
            'provider_id' => $this->getPaymentId(),
        ]);

        if ($extraPaymentModifier) {
            $extraPaymentModifier(
                $this->payment->fresh()
            );
        }

        if ($this->is_recurring) {
            return 'success';
        }
        return $this->getPaymentUrl();
    }

    public function prepareRecurringPayment(
        ...$params
    ): string {
        $this->is_recurring = true;
        return $this->preparePayment(
            ...$params
        );
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

        if (class_exists(config('payable.actions.before_redirect_to_confirmation_page'))) {
            $payment = config('payable.actions.before_redirect_to_confirmation_page')::handle($payment);
        }

        return redirect()->route(
            $route_name,
            [
                'status' => $response->getStatus(),
                'pid' => $payment->id,
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
            'canceled_at' => $this->getCanceledAtTimeStamp($payment),
            'expires_at' => $this->getExpiresAtTimeStamp($payment),
            'failed_at' => $this->getFailedAtTimeStamp($payment),
            'paid_at' => $this->getPaidAtTimeStamp($payment),
            'consumer_name' => $this->getConsumerName($payment),
            'consumer_account' => $this->getConsumerAccount($payment),
            'consumer_bic' => $this->getConsumerBic($payment),
            'payment_type_name' => $this->getPaymentTypeName($payment),
        ]);
    }

    protected function getPayableAmount(): int
    {
        return $this->payment->remaining_amount;
    }

    protected function getPayableAmountAsFloat(): float
    {
        return ($this->payment->remaining_amount / 100);
    }

    protected function getPayableDescription(): string
    {
        return $this->payableModel->getPayableDescription();
    }

    protected function getPayableIdentifier()
    {
        return $this->payableModel->id;
    }

    public function isTestPayment($testPayment = null): bool
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
        if (config('app.env') == 'local' && !config('payable.shared_with_expose')) {
            return null;
        }

        if (!$this->payment) {
            throw new Exception('Payment model hasnt been created yet. We can not create a webhook route');
        }

        $webhook_path = route('payable.webhook', [
            'payment' => $this->payment,
        ]);

        if (config('payable.shared_with_expose')) {
            $webhook_path = Str::replaceFirst(config('app.url'), config('payable.shared_with_expose'), $webhook_path);
        }

        return $webhook_path;
    }

    protected function redirectUrl(): string
    {
        return route('payable.return', [
            'payment' => $this->payment,
        ]);
    }

    protected function cancelUrl(): string
    {
        return route(
            config('payable.routes.payment_canceled'),
            [
                'pid' => $this->payment->id,
            ]
        );
    }

    protected function getCurrencyIso4217Code(): string
    {
        return 'EUR';
    }

    protected function getCountryCode(): string
    {
        return 'NL';
    }

    protected function getLocale(): string
    {
        return config('payable.locale');
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
        $provider = Payable::getProvider($payment->type);

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

    private function getCanceledAtTimeStamp(Payment $payment): ?Carbon
    {
        if ($payment->isCanceled()) {
            return $this->getCanceledAt($payment);
        }

        return null;
    }

    private function getExpiresAtTimeStamp(Payment $payment): ?Carbon
    {
        if ($payment->isExpired()) {
            return $this->getExpiresAt($payment);
        }

        return null;
    }

    private function getFailedAtTimeStamp(Payment $payment): ?Carbon
    {
        if ($payment->isFailed()) {
            return $this->getFailedAt($payment);
        }

        return null;
    }

    private function getPaidAtTimeStamp(Payment $payment): ?Carbon
    {
        if ($payment->isPaid()) {
            return $this->getPaidAt($payment);
        }

        return null;
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

    protected function storeResultPayload(Payment $payment, string $result = null): void
    {
        $payment->update([
            'result_payload' => $result,
        ]);
    }

    protected function storeResultPayloadText(Payment $payment, string $result = null): void
    {
        $payment->update([
            'result_payload_text' => $result,
        ]);
    }
}
