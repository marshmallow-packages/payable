<?php

namespace Marshmallow\Payable\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentStatus extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    /**
     * Relationships
     */
    public function payment()
    {
        return $this->belongsTo(config('payable.models.payment'), 'payment_id');
    }
}
