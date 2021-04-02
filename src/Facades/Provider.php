<?php

namespace Marshmallow\Payable\Facades;

class Provider extends \Illuminate\Support\Facades\Facade
{
    protected static function getFacadeAccessor()
    {
        return \Marshmallow\Payable\Providers\Provider::class;
    }
}
