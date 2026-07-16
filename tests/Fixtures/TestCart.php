<?php

namespace Marshmallow\Payable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Marshmallow\Payable\Traits\Payable;

/**
 * A minimal payable for tests that need a payment to point at something with
 * a total.
 */
class TestCart extends Model
{
    use Payable;

    protected $table = 'test_carts';

    protected $guarded = [];

    public function getTotalAmount(): int
    {
        return (int) $this->total_amount;
    }

    public function getPayableDescription(): string
    {
        return 'Test cart';
    }

    public function getCustomerName(): ?string
    {
        return null;
    }

    public function getCustomerEmail(): ?string
    {
        return null;
    }

    public function getCustomer(): ?Model
    {
        return null;
    }
}
