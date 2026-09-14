<?php

namespace Marshmallow\Payable\Tests\Unit;

use stdClass;
use Carbon\Carbon;
use Marshmallow\Payable\Models\Payment;
use PHPUnit\Framework\Attributes\Test;
use Marshmallow\Payable\Tests\TestCase;
use Marshmallow\Payable\Providers\Mollie;

/**
 * The timestamp getters read straight off whatever getPaymentStatus() gave
 * back. For a legacy ord_ order that is the raw stdClass of the Orders API,
 * so a field Mollie did not send must be a null, not an "Undefined property"
 * warning that Laravel throws and turns the webhook into a 500.
 */
class MollieTimestampsTest extends TestCase
{
    #[Test]
    public function an_expired_payment_without_expires_at_does_not_throw(): void
    {
        $info = new stdClass;
        $info->status = 'expired';

        $this->assertNull($this->mollieWith($info)->getExpiresAt(new Payment));
    }

    #[Test]
    public function the_expiry_prefers_the_moment_it_actually_expired(): void
    {
        /**
         * Mollie drops `expiresAt` once a payment has expired and sends
         * `expiredAt` instead - and Provider asks for this timestamp precisely
         * when the payment IS expired.
         */
        $info = new stdClass;
        $info->expiredAt = '2026-09-06T17:01:17+00:00';

        $this->assertTrue(
            Carbon::parse('2026-09-06T17:01:17+00:00')->equalTo(
                $this->mollieWith($info)->getExpiresAt(new Payment)
            )
        );
    }

    #[Test]
    public function the_expiry_falls_back_to_expires_at(): void
    {
        $info = new stdClass;
        $info->expiresAt = '2026-09-07T09:00:00+00:00';

        $this->assertTrue(
            Carbon::parse('2026-09-07T09:00:00+00:00')->equalTo(
                $this->mollieWith($info)->getExpiresAt(new Payment)
            )
        );
    }

    #[Test]
    public function every_timestamp_getter_returns_null_for_a_field_that_was_not_sent(): void
    {
        $mollie = $this->mollieWith(new stdClass);

        $this->assertNull($mollie->getCanceledAt(new Payment));
        $this->assertNull($mollie->getExpiresAt(new Payment));
        $this->assertNull($mollie->getFailedAt(new Payment));
        $this->assertNull($mollie->getPaidAt(new Payment));
    }

    #[Test]
    public function a_declared_but_empty_property_is_null_rather_than_now(): void
    {
        /**
         * The SDK resource declares every property, so a timestamp Mollie
         * did not send is null there. Carbon::parse(null) would quietly
         * return "now" and store a made-up moment.
         */
        $info = new stdClass;
        $info->paidAt = null;

        $this->assertNull($this->mollieWith($info)->getPaidAt(new Payment));
    }

    #[Test]
    public function a_timestamp_that_was_sent_is_parsed(): void
    {
        $info = new stdClass;
        $info->paidAt = '2026-09-01T12:34:56+00:00';

        $this->assertTrue(
            Carbon::parse('2026-09-01T12:34:56+00:00')->equalTo(
                $this->mollieWith($info)->getPaidAt(new Payment)
            )
        );
    }

    /**
     * A Mollie provider whose status read already happened, so no HTTP call
     * is made and the getters see exactly the object under test.
     */
    protected function mollieWith(object $info): Mollie
    {
        return new class($info) extends Mollie
        {
            public function __construct(object $info)
            {
                $this->payment_info_result = $info;
            }
        };
    }
}
