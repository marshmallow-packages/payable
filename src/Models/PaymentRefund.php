<?php

namespace Marshmallow\Payable\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentRefund extends Model
{
    use SoftDeletes;

    protected $table = 'payment_refunds';

    protected $guarded = [];

    /**
     * Relationships
     */
    public function payment()
    {
        return $this->belongsTo(config('payable.models.payment'), 'payment_id');
    }

    public function provider()
    {
        return $this->belongsTo(config('payable.models.payment_provider'), 'provider_id');
    }
}
