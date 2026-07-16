<?php

namespace Marshmallow\Payable\Tests\Unit;

use Exception;
use ReflectionClass;
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
    #[DataProvider('unimplementedProviderTypes')]
    public function it_rejects_a_provider_type_it_does_not_implement(string $type): void
    {
        $paymentType = $this->paymentTypeForProviderType($type);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('This provider is not implemented yet');

        (new Payable)->getProvider($paymentType);
    }

    /**
     * ADYEN is here because it used to be advertised as supported while
     * resolving to a provider class that never existed, so it fataled with
     * "Class not found" instead of being rejected.
     */
    public static function unimplementedProviderTypes(): array
    {
        return [
            'never released' => ['SOME_UNRELEASED_PSP'],
            'adyen' => ['ADYEN'],
        ];
    }

    #[Test]
    public function it_only_advertises_provider_types_it_can_resolve(): void
    {
        $advertised = array_values((new ReflectionClass(Payable::class))->getConstants());
        $resolvable = array_column(self::supportedProviders(), 0);

        sort($advertised);
        sort($resolvable);

        $this->assertSame(
            $resolvable,
            $advertised,
            'Every constant Payable declares must resolve to a provider.'
        );
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
