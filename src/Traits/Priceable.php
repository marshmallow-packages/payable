<?php

namespace Marshmallow\Payable\Traits;

use Marshmallow\Payable\Models\Payment;

trait Payable
{
    public function payments()
    {
        return $this->morphMany(Payment::class, 'payable');
    }
}
