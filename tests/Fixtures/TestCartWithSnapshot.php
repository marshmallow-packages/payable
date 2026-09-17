<?php

namespace Marshmallow\Payable\Tests\Fixtures;

/**
 * A payable that describes what a payment for it covers.
 */
class TestCartWithSnapshot extends TestCart
{
    public function getPayableSnapshot(): ?array
    {
        return [
            'cart_id' => $this->id,
            'total_amount' => $this->getTotalAmount(),
            'lines' => [
                ['description' => 'Blauwe trui', 'quantity' => 2, 'unit_amount' => 2500],
            ],
        ];
    }
}
