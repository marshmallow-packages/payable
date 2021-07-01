<?php

namespace Marshmallow\Payable;

use Exception;
use Marshmallow\Payable\Providers\Mollie;
use Marshmallow\Payable\Providers\Stripe;
use Marshmallow\Payable\Models\PaymentType;
use Marshmallow\Payable\Providers\Provider;
use Marshmallow\Payable\Providers\MultiSafePay;

class Payable
{
    public const MOLLIE = 'MOLLIE';
    public const MULTI_SAFE_PAY = 'MULTI_SAFE_PAY';
    public const STRIPE = 'STRIPE';

    public function getProvider(PaymentType $paymentType): Provider
    {
        switch ($paymentType->provider->type) {
            case self::MOLLIE:
                return new Mollie;
                break;

            case self::MULTI_SAFE_PAY:
                return new MultiSafePay;
                break;

            case self::STRIPE:
                return new Stripe;
                break;
        }

        throw new Exception("This provider is not implemented yet");
    }
}
