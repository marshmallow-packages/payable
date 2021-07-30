<?php

namespace Marshmallow\Payable;

use App\Models\User;
use Marshmallow\Payable\Payable;
use Marshmallow\Product\Models\Product;
use Marshmallow\Payable\Models\PaymentType;
use Marshmallow\Payable\Models\PaymentProvider;
use Marshmallow\Ecommerce\Cart\Models\ShoppingCart;

class PayableTest
{
    public function mollie($test = false, $api_key = null)
    {
        $cart = $this->getTestCart();
        $payment_type = $this->getPaymentType(Payable::MOLLIE);

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
        $provider = PaymentProvider::type($provider)->first();
        return $provider->types->first();
    }

    protected function getTestCart()
    {
        $user = User::first();
        $product = config('cart.models.product')::first();

        $cart = config('cart.models.shopping_cart')::completelyNew();
        $cart->connectUser($user);
        $cart->add($product, 4);

        return $cart;
    }
}
