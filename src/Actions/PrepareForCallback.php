<?php

namespace Marshmallow\Payable\Actions;

use Marshmallow\Payable\Models\Payment;

class PrepareForCallback
{
    public static function handle(Payment $payment): Payment
    {
        return $payment;
    }
}
