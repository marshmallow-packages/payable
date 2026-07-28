<?php

namespace Marshmallow\Payable\Tests\Fixtures;

/**
 * A payable that exposes an order number as payment reference, the way a
 * consuming application does when its back office reconciles on that number.
 */
class TestOrderCart extends TestCart
{
    public function getPayableIdentifier(): ?string
    {
        return $this->reference ? (string) $this->reference : null;
    }
}
