<?php

namespace Marshmallow\Payable;

use Marshmallow\Payable\Providers\Mollie;
use Marshmallow\Payable\Providers\Provider;

class Payable
{
    public function getProvider(): Provider
    {
        return new Mollie;
    }
}
