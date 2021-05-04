<?php

namespace Marshmallow\Payable\Events;

use Illuminate\Queue\SerializesModels;
use Marshmallow\Payable\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class PaymentStatusRefunded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $payment;

    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
    }
}
