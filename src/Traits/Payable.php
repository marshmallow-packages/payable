<?php

namespace Marshmallow\Payable\Traits;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Marshmallow\Payable\Models\Payment;
use Marshmallow\Payable\Models\PaymentType;
use Marshmallow\Payable\Facades\Payable as PayableHelper;

trait Payable
{
    public function paymentAllowed()
    {
        return true;
    }

    public function startPayment(
        PaymentType $paymentType,
        $testPayment = null,
        $apiKey = null,
        ?callable $extraPaymentDataCallback = null,
        ?callable $extraPaymentModifier = null,
        bool $is_recurring = false,
        bool $is_custom = false,
    ) {
        if (!$this->paymentAllowed()) {
            throw new Exception("Payment is not allowed at this point");
        }
        $provider = PayableHelper::getProvider($paymentType);

        $method = ($is_recurring) ? 'prepareRecurringPayment' : 'preparePayment';

        if ($is_custom) {
            $method = 'prepareCustomPayment';
        }

        return $provider->{$method}(
            $this,
            $paymentType,
            $testPayment,
            $apiKey,
            $extraPaymentDataCallback,
            $extraPaymentModifier
        );
    }

    public function startRecurringPayment(
        PaymentType $paymentType,
        $testPayment = null,
        $apiKey = null,
        ?callable $extraPaymentDataCallback = null,
        ?callable $extraPaymentModifier = null,
    ) {
        return $this->startPayment(
            paymentType: $paymentType,
            testPayment: $testPayment,
            apiKey: $apiKey,
            extraPaymentDataCallback: $extraPaymentDataCallback,
            extraPaymentModifier: $extraPaymentModifier,
            is_recurring: true
        );
    }

    public function payments()
    {
        return $this->morphMany(config('payable.models.payment'), 'payable');
    }

    /**
     * A frozen description of what a payment for this model covers. It is
     * stored on the payment as `payable_snapshot` the moment the payment
     * starts, so a webhook can prove what the settled amount bought even when
     * the payable changed or vanished in the meantime. Override it to return
     * the lines, amounts and parties the payment is for; null stores nothing.
     *
     * @return array<string, mixed>|null
     */
    public function getPayableSnapshot(): ?array
    {
        return null;
    }

    public abstract function getTotalAmount(): int;
    public abstract function getPayableDescription(): string;
    public abstract function getCustomerName(): ?string;
    public abstract function getCustomerEmail(): ?string;
    public abstract function getCustomer(): ?Model;
}
