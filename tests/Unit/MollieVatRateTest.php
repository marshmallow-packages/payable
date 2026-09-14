<?php

namespace Marshmallow\Payable\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Marshmallow\Payable\Tests\TestCase;
use Marshmallow\Payable\Providers\Mollie;

/**
 * The Mollie SDK's payload factory reads fields with a truthiness check, so
 * a vatRate of "0" is dropped from the request while its vatAmount is kept -
 * and Mollie then rejects the line. The rate has to go out in Mollie's own
 * two-decimal shape, where 0% is the truthy string "0.00".
 */
class MollieVatRateTest extends TestCase
{
    #[Test]
    public function a_zero_rate_is_sent_as_a_truthy_two_decimal_string(): void
    {
        $this->assertSame('0.00', $this->mollie()->formatVatRate(0));
        $this->assertSame('0.00', $this->mollie()->formatVatRate(0.0));
        $this->assertSame('0.00', $this->mollie()->formatVatRate('0'));
        $this->assertNotEmpty($this->mollie()->formatVatRate(0));
    }

    #[Test]
    public function a_rate_is_formatted_the_way_mollie_documents_it(): void
    {
        $this->assertSame('21.00', $this->mollie()->formatVatRate(21));
        $this->assertSame('21.00', $this->mollie()->formatVatRate(21.0));
        $this->assertSame('9.00', $this->mollie()->formatVatRate('9'));
        $this->assertSame('5.50', $this->mollie()->formatVatRate(5.5));
    }

    protected function mollie(): Mollie
    {
        return new class extends Mollie
        {
            public function __construct()
            {
            }
        };
    }
}
