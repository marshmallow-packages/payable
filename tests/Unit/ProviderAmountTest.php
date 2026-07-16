<?php

namespace Marshmallow\Payable\Tests\Unit;

use Marshmallow\Payable\Providers\Provider;
use Marshmallow\Payable\Tests\TestCase;

class ProviderAmountTest extends TestCase
{
    /**
     * @test
     *
     * @dataProvider decimalAmounts
     */
    public function it_converts_decimal_amounts_to_cents_without_losing_a_cent($amount, int $expected)
    {
        $this->assertSame($expected, (new Provider)->formatDecimalStringToCent($amount));
    }

    /**
     * @return array<string, array{0: string|float|int, 1: int}>
     */
    public static function decimalAmounts(): array
    {
        return [
            'string with trailing zero' => ['8.70', 870],
            'string below one euro' => ['0.29', 29],
            'string with nines' => ['19.99', 1999],
            'string with repeating decimal' => ['33.30', 3330],
            'string whole euros' => ['10.00', 1000],
            'string zero' => ['0.00', 0],
            'float' => [8.70, 870],
            'integer' => [12, 1200],
            'large amount' => ['12345.67', 1234567],
        ];
    }
}
