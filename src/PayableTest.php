<?php

namespace Marshmallow\Payable;

use Marshmallow\Payable\Payable;
use Marshmallow\Addressable\Models\AddressType;

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

    public function multisafepay($test = false, $api_key = null)
    {
        $cart = $this->getTestCart();
        $payment_type = $this->getPaymentType(Payable::MULTI_SAFE_PAY);
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



    public function buckaroo($test = true)
    {
        $cart = $this->getTestCart();
        $payment_type = $this->getPaymentType(Payable::BUCKAROO);

        if ($this->recurring) {
            return $cart->startRecurringPayment(
                paymentType: $payment_type,
                testPayment: $test
            );
        }

        return $cart->startPayment(
            paymentType: $payment_type,
            testPayment: $test,
            extraPaymentDataCallback: function () {
                return (object) [
                    'service' => 'ideal',
                    'issuer' => 'BUNQNL2A',
                ];
            }
        );
    }

    protected function getPaymentType($provider)
    {
        $provider = config('payable.models.payment_provider')::type($provider)->first();
        return $provider->types->first();
    }

    protected function getTestCart()
    {
        $user = config('cart.models.user')::first();

        if ($user->addresses->count() == 0) {
            $user->addresses()->create([
                'address_type_id' => AddressType::where('type', 'INVOICE')->first()->id,
                'name' => 'Stef van Esch',
                'first_name' => 'Stef',
                'last_name' => 'van Esch',
                'address_line_1' => 'Da Costastraat',
                'address_line_2' => '8',
                'address_line_3' => null,
                'address_line_4' => null,
                'city' => 'Alphen aan den Rijn',
                'state' => 'Zuid-Holland',
                'postal_code' => '2406AT',
                'country_id' => null,
            ]);
            $user->addresses()->create([
                'address_type_id' => AddressType::where('type', 'SHIPPING')->first()->id,
                'name' => 'Stef van Esch',
                'first_name' => 'Stef',
                'last_name' => 'van Esch',
                'address_line_1' => 'Da Costastraat',
                'address_line_2' => '8',
                'address_line_3' => null,
                'address_line_4' => null,
                'city' => 'Alphen aan den Rijn',
                'state' => 'Zuid-Holland',
                'postal_code' => '2406AT',
                'country_id' => null,
            ]);
        }

        $product = config('cart.models.product')::first();

        $cart = config('cart.models.shopping_cart')::completelyNew();
        $cart->connectUser($user);
        $cart->add($product, 4);
        $cart->getCustomer()->update([
            'email' => 'stef@marshmallow.dev',
        ]);

        return $cart;
    }
}
