<?php

namespace Marshmallow\Payable;

use App\Models\User;
use Marshmallow\Product\Models\Product;
use Marshmallow\Payable\Models\PaymentType;
use Marshmallow\Ecommerce\Cart\Models\ShoppingCart;

class PayableTest
{
    public function mollie($test = false, $api_key = null)
    {
        $user = User::first();
        $product = Product::first();
        $payment_type = PaymentType::first();

        $cart = ShoppingCart::completelyNew();
        $cart->connectUser($user);
        $cart->add($product, 4);
        return $cart->startPayment($payment_type, $test, $api_key);
    }
}
