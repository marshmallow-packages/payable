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
    public $event_type;

    public function __construct($event_type, $payable_external_id, $payload_data)
    {
        $this->event_type = $event_type;
        $this->payable_external_id = $payable_external_id;
        $this->payload_data = $payload_data;
    }
}
