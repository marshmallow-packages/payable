<?php

namespace Marshmallow\Payable\Tests\Unit;

use Marshmallow\Payable\Payable;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Database\Schema\Blueprint;
use Marshmallow\Payable\Tests\TestCase;
use Marshmallow\Payable\Models\Payment;
use Marshmallow\Payable\Models\PaymentType;
use Marshmallow\Payable\Models\PaymentProvider;
use Marshmallow\Payable\Tests\Fixtures\TestCart;

/**
 * A provider reporting "paid" only means the transaction succeeded, not that
 * it covered the full amount. isPaid() must check both.
 */
class PaidInFullTest extends TestCase
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
    public function a_payment_covering_the_total_is_paid(): void
    {
        $payment = $this->paymentFor(total: 10000, paid: 10000);

        $this->assertTrue($payment->paidInFull());
        $this->assertTrue($payment->isPaid());
    }

    #[Test]
    public function a_payment_short_of_the_total_is_not_paid(): void
    {
        $payment = $this->paymentFor(total: 10000, paid: 9999);

        $this->assertFalse($payment->paidInFull());
        $this->assertFalse(
            $payment->isPaid(),
            'A payment the provider called paid, but which is a cent short, must not count as paid.'
        );
    }

    #[Test]
    public function a_payment_that_is_not_marked_paid_is_not_paid_however_much_was_settled(): void
    {
        $payment = $this->paymentFor(total: 10000, paid: 10000, status: Payment::STATUS_OPEN);

        $this->assertTrue($payment->paidInFull());
        $this->assertFalse($payment->isPaid());
    }

    #[Test]
    public function a_payment_whose_payable_is_gone_is_not_paid_in_full(): void
    {
        /**
         * Nothing left to compare the settled amount against. Before, this
         * fataled on a null relation rather than answering.
         */
        $payment = $this->paymentFor(total: 10000, paid: 10000);
        $payment->payable->delete();

        $this->assertFalse($payment->fresh()->paidInFull());
    }

    protected function paymentFor(int $total, int $paid, string $status = Payment::STATUS_PAID): Payment
    {
        $cart = TestCart::create(['total_amount' => $total]);

        $provider = PaymentProvider::create([
            'name' => 'Mollie',
            'slug' => 'mollie-' . $total . '-' . $paid,
            'type' => Payable::MOLLIE,
            'active' => true,
        ]);

        $type = PaymentType::create([
            'payment_provider_id' => $provider->id,
            'name' => 'iDEAL',
            'slug' => 'ideal-' . $total . '-' . $paid,
            'active' => true,
        ]);

        return Payment::create([
            'payable_type' => TestCart::class,
            'payable_id' => $cart->id,
            'payment_provider_id' => $provider->id,
            'payment_type_id' => $type->id,
            'simple_checkout' => false,
            'total_amount' => $total,
            'paid_amount' => $paid,
            'status' => $status,
            'started' => now(),
            'start_ip' => '127.0.0.1',
        ]);
    }
}
