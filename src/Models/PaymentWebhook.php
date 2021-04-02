<?php

namespace Marshmallow\Payable\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentWebhook extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'get_payload' => 'array',
        'post_payload' => 'array',
    ];

    /**
     * Relationships
     */
    public function payment()
    {
        return $this->belongsTo(config('payable.models.payment'), 'payment_id');
    }
}
