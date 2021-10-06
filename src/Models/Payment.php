<?php

namespace Marshmallow\Payable\Models;

use ReflectionClass;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Marshmallow\Payable\Events\PaymentStatusOpen;
use Marshmallow\Payable\Events\PaymentStatusPaid;
use Marshmallow\Payable\Events\PaymentStatusFailed;
use Marshmallow\Payable\Events\PaymentStatusExpired;
use Marshmallow\Payable\Events\PaymentStatusUnknown;
use Marshmallow\Payable\Events\PaymentStatusCanceled;
use Marshmallow\Payable\Events\PaymentStatusRefunded;

class Payment extends Model
{
    use SoftDeletes;

    const STATUS_OPEN = 'open';
    const STATUS_PAID = 'paid';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELED = 'canceled';
    const STATUS_EXPIRED = 'expired';
    const STATUS_REFUNDED = 'refunded';

    protected $table = 'payments';

    protected $guarded = [];

    protected $casts = [
        'started' => 'datetime',
        'status_changed_at' => 'datetime',
        'canceled_at' => 'datetime',
        'expires_at' => 'datetime',
        'failed_at' => 'datetime',
        'paid_at' => 'datetime',
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

    public function triggerStatusEvent()
    {
        if ($this->isOpen()) {
            event(new PaymentStatusOpen($this));
        } elseif ($this->isPaid()) {
            event(new PaymentStatusPaid($this));
        } elseif ($this->isFailed()) {
            event(new PaymentStatusFailed($this));
        } elseif ($this->isCanceled()) {
            event(new PaymentStatusCanceled($this));
        } elseif ($this->isExpired()) {
            event(new PaymentStatusExpired($this));
        } elseif ($this->isRefunded()) {
            event(new PaymentStatusRefunded($this));
        } else {
            event(new PaymentStatusUnknown($this));
        }
    }

    public function isOpen()
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isPaid()
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isFailed()
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isCanceled()
    {
        return $this->status === self::STATUS_CANCELED;
    }

    public function isExpired()
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    public function isRefunded()
    {
        return $this->status === self::STATUS_REFUNDED;
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

        static::created(function ($payment) {
            $payment->triggerStatusEvent();
        });

        static::updated(function ($payment) {
            if ($payment->isDirty('status')) {
                $payment->triggerStatusEvent();
            }
        });

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
