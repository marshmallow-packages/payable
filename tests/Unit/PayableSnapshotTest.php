<?php

namespace Marshmallow\Payable\Tests\Unit;

use Marshmallow\Payable\Payable;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Database\Schema\Blueprint;
use Marshmallow\Payable\Tests\TestCase;
use Marshmallow\Payable\Models\Payment;
use Marshmallow\Payable\Models\PaymentType;
use Marshmallow\Payable\Providers\Provider;
use Marshmallow\Payable\Models\PaymentProvider;
use Marshmallow\Payable\Tests\Fixtures\TestCart;
use Marshmallow\Payable\Tests\Fixtures\TestCartWithSnapshot;

/**
 * A payment records what it was started for. The payable may change (or be
 * deleted) between the redirect and the webhook; the snapshot on the payment
 * is what the settled amount actually bought.
 */
class PayableSnapshotTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Schema::create('test_carts', function (Blueprint $table): void {
            $table->id();
            $table->integer('total_amount');
            $table->timestamps();
        });
    }

    #[Test]
    public function it_freezes_the_payable_snapshot_onto_the_payment_when_it_starts(): void
    {
        $cart = TestCartWithSnapshot::create(['total_amount' => 5000]);

        $payment = (new Provider)->prepareCustomPayment($cart, $this->paymentType());

        $this->assertSame([
            'cart_id' => $cart->id,
            'total_amount' => 5000,
            'lines' => [
                ['description' => 'Blauwe trui', 'quantity' => 2, 'unit_amount' => 2500],
            ],
        ], $payment->fresh()->payable_snapshot);
    }

    #[Test]
    public function the_snapshot_survives_changes_to_the_payable(): void
    {
        $cart = TestCartWithSnapshot::create(['total_amount' => 5000]);
        $payment = (new Provider)->prepareCustomPayment($cart, $this->paymentType());

        $cart->update(['total_amount' => 9999]);
        $cart->delete();

        $this->assertSame(5000, Payment::findOrFail($payment->id)->payable_snapshot['total_amount']);
        $this->assertSame(5000, $payment->fresh()->total_amount);
    }

    #[Test]
    public function a_payable_without_a_snapshot_stores_nothing(): void
    {
        $cart = TestCart::create(['total_amount' => 5000]);

        $payment = (new Provider)->prepareCustomPayment($cart, $this->paymentType());

        $this->assertNull($cart->getPayableSnapshot());
        $this->assertNull($payment->fresh()->payable_snapshot);
    }

    protected function paymentType(): PaymentType
    {
        $provider = PaymentProvider::create([
            'name' => 'Mollie',
            'slug' => 'mollie-' . uniqid(),
            'type' => Payable::MOLLIE,
            'active' => true,
        ]);

        return PaymentType::create([
            'payment_provider_id' => $provider->id,
            'name' => 'iDEAL',
            'slug' => 'ideal-' . uniqid(),
            'simple_checkout' => false,
            'active' => true,
        ]);
    }
}
