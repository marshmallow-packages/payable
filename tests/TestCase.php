<?php

namespace Marshmallow\Payable\Tests;

use Illuminate\Foundation\Application;
use Marshmallow\Payable\PayableServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Setup the test environment.
     */
    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @param Application $app
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [PayableServiceProvider::class];
    }
}
