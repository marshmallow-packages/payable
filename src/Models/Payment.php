<?php

namespace Marshmallow\Payable\Models;

use ReflectionClass;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;

    const STATUS_OPEN = 'open';
    const STATUS_PAID = 'paid';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELED = 'canceled';
    const STATUS_EXPIRED = 'expired';

    protected $table = 'payments';

    protected $guarded = [];

    protected $casts = [
        'started' => 'datetime',
        'status_changed_at' => 'datetime',
    ];

    public function logCallback(Request $request)
    {
        $this->webhooks()->create([
            'uri' => $request->url(),
            'get_payload' => $_GET,
            'post_payload' => $_POST,
            'status' => app('Illuminate\Http\Response')->status(),
            'return_ip' => $request->ip(),
        ]);
    }

    public static function getKnownStatusses(): array
    {
        $class = new ReflectionClass(__CLASS__);
        return collect($class->getConstants())->reject(function ($value, $name) {
            return (Str::substr($name, 0, Str::length('STATUS_')) !== 'STATUS_');
        })->toArray();
    }

    /**
     * Relationships
     */
    public function payable()
    {
        return $this->morphTo();
    }

    public function provider()
    {
        return $this->belongsTo(config('payable.models.payment_provider'), 'payment_provider_id');
    }

    public function type()
    {
        return $this->belongsTo(config('payable.models.payment_type'), 'payment_type_id');
    }

    public function webhooks()
    {
        return $this->hasMany(config('payable.models.payment_webhook'), 'payment_id');
    }

    public function statusses()
    {
        return $this->hasMany(config('payable.models.payment_status'), 'payment_id');
    }

    /**
     * Model setup
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            if (empty($payment->{$payment->getKeyName()})) {
                $payment->{$payment->getKeyName()} = Str::uuid()->toString();
            }
        });

        static::saving(function ($payment) {
            $payment->remaining_amount = $payment->total_amount - $payment->paid_amount;
            $payment->completed = ($payment->remaining_amount == 0) ? true : false;
        });
    }

    public function getIncrementing()
    {
        return false;
    }

    public function getKeyType()
    {
        return 'string';
    }

    public function getRouteKeyName()
    {
        return 'id';
    }
}
