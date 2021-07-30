<?php

namespace Marshmallow\Payable\Services\Ippies\Facades;

use Marshmallow\Payable\Services\Ippies\IppiesApi;

class Ippies extends \Illuminate\Support\Facades\Facade
{
    protected static function getFacadeAccessor()
    {
        return IppiesApi::class;
    }
}
