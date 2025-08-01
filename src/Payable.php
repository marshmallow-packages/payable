<?php

namespace Marshmallow\Payable;

use Exception;
use Marshmallow\Payable\Providers\Adyen;
use Marshmallow\Payable\Providers\Ippies;
use Marshmallow\Payable\Providers\Mollie;
use Marshmallow\Payable\Providers\Stripe;
use Marshmallow\Payable\Models\PaymentType;
use Marshmallow\Payable\Providers\Buckaroo;
use Marshmallow\Payable\Providers\Provider;
use Marshmallow\Payable\Providers\MultiSafePay;

class Payable
{
    public const MOLLIE = 'MOLLIE';
    public const MULTI_SAFE_PAY = 'MULTI_SAFE_PAY';
    public const STRIPE = 'STRIPE';
    public const IPPIES = 'IPPIES';
    public const ADYEN = 'ADYEN';
    public const BUCKAROO = 'BUCKAROO';

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

            case self::IPPIES:
                return new Ippies;
                break;


            case self::ADYEN:
                return new Adyen;
                break;

            case self::BUCKAROO:
                return new Buckaroo;
                break;
        }

        throw new Exception("This provider is not implemented yet");
    }
}
