<?php

namespace Marshmallow\Payable\Facades;

class Payable extends \Illuminate\Support\Facades\Facade
{
    protected static function getFacadeAccessor()
    {
        return \Marshmallow\Payable\Payable::class;
    }
}
