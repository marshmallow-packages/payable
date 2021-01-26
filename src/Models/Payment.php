<?php

namespace Marshmallow\Payable\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        // 'valid_from' => 'datetime',
        // 'valid_till' => 'datetime',
    ];

    public function payable()
    {
        return $this->morphTo();
    }
}
