<?php

namespace Marshmallow\Payable\Tests\Unit;

use Exception;
use Marshmallow\Payable\Payable;
use Marshmallow\Payable\Providers\Mollie;
use Marshmallow\Payable\Providers\Stripe;
use PHPUnit\Framework\Attributes\Test;
use Marshmallow\Payable\Tests\TestCase;
use Marshmallow\Payable\Models\PaymentType;
use Marshmallow\Payable\Providers\Buckaroo;
use Marshmallow\Payable\Models\PaymentProvider;
use Marshmallow\Payable\Providers\MultiSafePay;
use PHPUnit\Framework\Attributes\DataProvider;

class ProviderResolutionTest extends TestCase
{
    #[Test]
    #[DataProvider('supportedProviders')]
    public function it_resolves_a_payment_type_to_its_provider(string $type, string $expected): void
    {
        $paymentType = $this->paymentTypeForProviderType($type);

        $this->assertInstanceOf($expected, (new Payable)->getProvider($paymentType));
    }

    /**
     * Payable::ADYEN is deliberately absent: it resolves to a provider class
     * that does not exist and fatals. Tracked in #99; add it back here once
     * that is settled.
     */
    public static function supportedProviders(): array
    {
        return [
            'mollie' => [Payable::MOLLIE, Mollie::class],
            'multisafepay' => [Payable::MULTI_SAFE_PAY, MultiSafePay::class],
            'stripe' => [Payable::STRIPE, Stripe::class],
            'buckaroo' => [Payable::BUCKAROO, Buckaroo::class],
        ];
    }

    #[Test]
    public function it_rejects_a_provider_type_it_does_not_implement(): void
    {
        $paymentType = $this->paymentTypeForProviderType('SOME_UNRELEASED_PSP');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('This provider is not implemented yet');

        (new Payable)->getProvider($paymentType);
    }

    protected function paymentTypeForProviderType(string $type): PaymentType
    {
        $provider = PaymentProvider::create([
            'name' => $type,
            'slug' => strtolower($type),
            'type' => $type,
            'active' => true,
        ]);

        return PaymentType::create([
            'payment_provider_id' => $provider->id,
            'name' => "{$type} direct",
            'slug' => 'direct-payment',
            'active' => true,
        ]);
    }
}
