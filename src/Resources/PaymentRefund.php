<?php

namespace Marshmallow\Payable\Resources;

class PaymentRefund
{
    public function __construct(
        protected string $provider_id,
        protected string $status
    ) {
        //
    }

    public function getProviderId()
    {
        return $this->provider_id;
    }

    public function getStatus()
    {
        return $this->status;
    }
}
