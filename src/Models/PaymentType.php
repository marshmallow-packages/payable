<?php

namespace Marshmallow\Payable\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentType extends Model
{
    use SoftDeletes;

    public const COMMISSION_PRICE = 'COMMISSION_PRICE';

    public const COMMISSION_PERCENTAGE = 'COMMISSION_PERCENTAGE';

    protected $guarded = [];

    public function provider()
    {
        return $this->belongsTo(config('payable.models.payment_provider'), 'payment_provider_id');
    }

    public function getIcon()
    {
        return asset('storage/' . $this->icon);
    }
}
