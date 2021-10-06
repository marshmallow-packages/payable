<?php

namespace Marshmallow\Payable;

use Marshmallow\Payable\Payable;

class PayableTest
{
    protected $recurring = false;

    public function recurring($recurring = true)
    {
        $this->recurring = $recurring;
        return $this;
    }

    public function mollie($test = false, $api_key = null)
    {
        $cart = $this->getTestCart();
        $payment_type = $this->getPaymentType(Payable::MOLLIE);
        return $cart->startPayment($payment_type, $test, $api_key);
    }

    public function adyen($test = false, $api_key = null)
    {
        $cart = $this->getTestCart();
        $payment_type = $this->getPaymentType(Payable::ADYEN);
        if ($this->recurring) {
            return $cart->startRecurringPayment($payment_type, $test, $api_key);
        }
        return $cart->startPayment($payment_type, $test, $api_key);
    }

    public function ippies($test = false, $api_key = null)
    {
        $cart = $this->getTestCart();
        $payment_type = $this->getPaymentType(Payable::IPPIES);

        return $cart->startPayment($payment_type, $test, $api_key);
    }

    public function paypal($test = false, $api_key = null)
    {
        $cart = $this->getTestCart();
        $payment_type = $this->getPaymentType(Payable::PAYPAL);

        return $cart->startPayment($payment_type, $test, $api_key);
    }

    protected function getPaymentType($provider)
    {
        $provider = config('cart.models.payment_provider')::type($provider)->first();
        return $provider->types->first();
    }

    protected function getTestCart()
    {
        $user = config('cart.models.user')::first();
        $product = config('cart.models.product')::first();

        $cart = config('cart.models.shopping_cart')::completelyNew();
        $cart->connectUser($user);
        $cart->add($product, 4);

        return $cart;
    }
}
