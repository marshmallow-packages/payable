<?php

namespace Marshmallow\Payable\Http\Responses;

use Exception;
use Marshmallow\Payable\Models\Payment;

class PaymentStatusResponse
{
    protected $status;
    protected $paid_amount;

    public function __construct(string $status, int $paid_amount)
    {
        $this->status = $status;
        $this->paid_amount = $paid_amount;
        $this->checkIfStatusExists($status);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getPaidAmount(): string
    {
        return $this->paid_amount;
    }

    protected function checkIfStatusExists($status)
    {
        $statusses = config('payable.models.payment')::getKnownStatusses();
        if (!in_array($status, $statusses)) {
            throw new Exception("Unknown status {$status} provided");
        }
    }
}
