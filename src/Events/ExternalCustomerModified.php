<?php

namespace Marshmallow\Payable\Events;

use Illuminate\Queue\SerializesModels;
use Marshmallow\Payable\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class ExternalCustomerModified
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $payable_external_id;
    public $payload_data;

    public function __construct($payable_external_id, $payload_data)
    {
        $this->payable_external_id = $payable_external_id;
        $this->payload_data = $payload_data;
    }
}
