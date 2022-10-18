<?php

namespace Marshmallow\Payable\Models\Providers\Buckaroo;

use Illuminate\Database\Eloquent\Model;

class BuckarooSubscription extends Model
{
    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'resume_date' => 'date',
        'subscriptions' => 'array',
        'services' => 'array',
        'custom_parameters' => 'array',
        'additional_parameters' => 'array',
        'request_errors' => 'array',
    ];
}
