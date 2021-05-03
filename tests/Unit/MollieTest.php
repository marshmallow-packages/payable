<?php

namespace Marshmallow\Payable\Tests\Unit;

use Marshmallow\Payable\Tests\TestCase;
use Marshmallow\Payable\Facades\Payable;
use Marshmallow\Payable\Providers\Mollie;
use Marshmallow\Payable\Facades\PayableTest;

class MollieTest extends TestCase
{
    /** @test */
    public function it_can_initialize_mollie_as_payment()
    {
        $api = Payable::getProvider();
        $this->assertInstanceOf(Mollie::class, $api);
    }

    /** @test */
    public function is_can_create_a_payment_url()
    {
        $redirect = PayableTest::mollie();
        dd($redirect);
    }
}
