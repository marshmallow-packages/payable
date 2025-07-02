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
        callable $extraPaymentDataCallback = null,
        callable $extraPaymentModifier = null,
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
        callable $extraPaymentDataCallback = null,
        callable $extraPaymentModifier = null,
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

    public abstract function getTotalAmount(): int;
    public abstract function getPayableDescription(): string;
    public abstract function getCustomerName(): ?string;
    public abstract function getCustomerEmail(): ?string;
    public abstract function getCustomer(): ?Model;
}
