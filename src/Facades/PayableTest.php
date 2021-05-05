<?php

namespace Marshmallow\Payable\Facades;

class PayableTest extends \Illuminate\Support\Facades\Facade
{
    protected static function getFacadeAccessor()
    {
        return \Marshmallow\Payable\PayableTest::class;
    }
}
