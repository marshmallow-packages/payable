<?php

namespace Marshmallow\Payable\Models;

use Marshmallow\Sluggable\HasSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentProvider extends Model
{
    use HasSlug;
    use SoftDeletes;

    public const PROVIDER_MOLLIE = 'MOLLIE';

    public const PROVIDER_CUSTOM = 'PROVIDER_CUSTOM';

    public const PROVIDER_MULTI_SAFE_PAY = 'PROVIDER_MULTI_SAFE_PAY';

    protected $guarded = [];

    public function scopeType(Builder $builder, string $type)
    {
        $builder->where('type', $type);
    }

    public function types()
    {
        return $this->hasMany(config('payable.models.payment_type'));
    }
}
