<?php

namespace Marshmallow\Payable\Tests\Unit;

use Exception;
use ReflectionMethod;
use ReflectionProperty;
use OnlinePayments\Sdk\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use OnlinePayments\Sdk\Merchant\MerchantClient;
use PHPUnit\Framework\Attributes\Test;
use Marshmallow\Payable\Tests\TestCase;
use Marshmallow\Payable\Models\Payment;
use Marshmallow\Payable\Providers\Worldline;
use Marshmallow\Payable\Models\PaymentType;
use Marshmallow\Payable\Models\PaymentProvider;
use Marshmallow\Payable\Tests\Fixtures\TestCart;
use Marshmallow\Payable\Tests\Fixtures\TestOrderCart;
use PHPUnit\Framework\Attributes\DataProvider;
use OnlinePayments\Sdk\Webhooks\SignatureValidationException;

class WorldlineTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('payable.worldline.webhook_key_id', 'test-key-id');
        $app['config']->set('payable.worldline.webhook_secret', 'test-secret');
        $app['config']->set('payable.worldline.merchant_id', 'test-merchant');
        $app['config']->set('payable.worldline.api_key_id', 'test-api-key');
        $app['config']->set('payable.worldline.api_secret', 'test-api-secret');
        $app['config']->set('payable.worldline.api_endpoint', 'https://payment.preprod.direct.worldline-solutions.com');
    }

    public function setUp(): void
    {
        parent::setUp();

        Schema::create('test_carts', function (Blueprint $table): void {
            $table->id();
            $table->integer('total_amount')->default(0);
            $table->string('reference')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Builds the SDK client from config, without touching the network. This
     * would have caught the provider importing an authenticator class that
     * does not exist: nothing else in the suite constructs the client.
     */
    #[Test]
    public function it_builds_an_sdk_client_from_config(): void
    {
        $provider = new Worldline;

        $method = new ReflectionMethod($provider, 'getClient');
        $method->setAccessible(true);

        $this->assertInstanceOf(Client::class, $method->invoke($provider));
    }

    #[Test]
    public function it_builds_a_merchant_client_for_the_configured_merchant(): void
    {
        $provider = new Worldline;

        $method = new ReflectionMethod($provider, 'merchantClient');
        $method->setAccessible(true);

        $this->assertInstanceOf(MerchantClient::class, $method->invoke($provider));
    }

    #[Test]
    #[DataProvider('statusMap')]
    public function it_maps_worldline_statuses_to_internal_statuses(string $worldline, string $internal): void
    {
        $this->assertSame($internal, (new Worldline)->convertStatus($worldline));
    }

    public static function statusMap(): array
    {
        return [
            'created is open' => ['CREATED', Payment::STATUS_OPEN],
            'redirected is open' => ['REDIRECTED', Payment::STATUS_OPEN],
            'pending capture is open' => ['PENDING_CAPTURE', Payment::STATUS_OPEN],
            'captured is paid' => ['CAPTURED', Payment::STATUS_PAID],
            'paid is paid' => ['PAID', Payment::STATUS_PAID],
            'cancelled is canceled' => ['CANCELLED', Payment::STATUS_CANCELED],
            'reversed is canceled' => ['REVERSED', Payment::STATUS_CANCELED],
            'rejected is failed' => ['REJECTED', Payment::STATUS_FAILED],
            'refunded is refunded' => ['REFUNDED', Payment::STATUS_REFUNDED],
        ];
    }

    #[Test]
    public function it_throws_on_an_unknown_worldline_status(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unknown payment status SOMETHING_NEW');

        (new Worldline)->convertStatus('SOMETHING_NEW');
    }

    #[Test]
    public function it_uses_the_payable_identifier_as_merchant_reference(): void
    {
        $cart = TestOrderCart::create(['total_amount' => 1000, 'reference' => '3000123']);

        $this->assertSame('3000123', $this->merchantReferenceFor($cart));
    }

    /**
     * Covers the actual hosted checkout request, not just the helper: the
     * initial release shipped a merchantReference() helper that createPayment
     * never called, which only an assertion on the built request catches.
     */
    #[Test]
    public function it_sends_the_payable_identifier_as_merchant_reference_to_worldline(): void
    {
        $cart = TestOrderCart::create(['total_amount' => 1000, 'reference' => '3000123']);
        $payment = $this->createPaymentRecord();

        $provider = new Worldline;

        $payableProperty = new ReflectionProperty($provider, 'payableModel');
        $payableProperty->setAccessible(true);
        $payableProperty->setValue($provider, $cart);

        $paymentProperty = new ReflectionProperty($provider, 'payment');
        $paymentProperty->setAccessible(true);
        $paymentProperty->setValue($provider, $payment);

        $request = $provider->buildHostedCheckoutRequest();

        $this->assertSame('3000123', $request->getOrder()->getReferences()->getMerchantReference());
    }

    #[Test]
    public function it_falls_back_to_the_payment_id_when_the_payable_has_no_identifier(): void
    {
        $plain_cart = TestCart::create(['total_amount' => 1000]);
        $empty_identifier_cart = TestOrderCart::create(['total_amount' => 1000, 'reference' => null]);

        $payment_id = $this->createPaymentRecord()->id;

        $this->assertSame($payment_id, $this->merchantReferenceFor($plain_cart, $payment_id));
        $this->assertSame($payment_id, $this->merchantReferenceFor($empty_identifier_cart, $payment_id));
    }

    #[Test]
    public function it_resolves_the_webhook_payment_through_the_hosted_checkout_id(): void
    {
        $payment = $this->createPaymentRecord(['provider_id' => 'hc-123']);

        $request = $this->signedWebhookRequest('3000123', 'test-secret', 'test-key-id', 'hc-123_0');

        $resolved = (new Worldline)->resolvePaymentFromWebhook($request);

        $this->assertNotNull($resolved);
        $this->assertSame($payment->id, $resolved->id);
    }

    #[Test]
    public function it_resolves_the_payment_a_signed_webhook_refers_to(): void
    {
        $payment = $this->createPaymentRecord();

        $request = $this->signedWebhookRequest($payment->id, 'test-secret', 'test-key-id');

        $resolved = (new Worldline)->resolvePaymentFromWebhook($request);

        $this->assertNotNull($resolved);
        $this->assertSame($payment->id, $resolved->id);
    }

    #[Test]
    public function it_resolves_the_webhook_payment_through_the_payable_identifier_as_last_resort(): void
    {
        $cart = TestOrderCart::create(['total_amount' => 1000, 'reference' => '3000123']);

        $payment = $this->createPaymentRecord([
            'provider_id' => 'hc-999',
            'status' => Payment::STATUS_OPEN,
            'payable_type' => TestOrderCart::class,
            'payable_id' => $cart->id,
        ]);

        // Webhook payment id in an unrecognisable format, reference is the order number.
        $request = $this->signedWebhookRequest('3000123', 'test-secret', 'test-key-id', 'unrecognisable-format');

        $resolved = (new Worldline)->resolvePaymentFromWebhook($request);

        $this->assertNotNull($resolved);
        $this->assertSame($payment->id, $resolved->id);
    }

    #[Test]
    public function it_ignores_a_webhook_it_cannot_resolve_to_exactly_one_payment(): void
    {
        $cart = TestOrderCart::create(['total_amount' => 1000, 'reference' => '3000123']);

        foreach (['hc-1', 'hc-2'] as $provider_id) {
            $this->createPaymentRecord([
                'provider_id' => $provider_id,
                'status' => Payment::STATUS_OPEN,
                'payable_type' => TestOrderCart::class,
                'payable_id' => $cart->id,
            ]);
        }

        $request = $this->signedWebhookRequest('3000123', 'test-secret', 'test-key-id', 'unrecognisable-format');

        $this->assertNull((new Worldline)->resolvePaymentFromWebhook($request));
    }

    protected function merchantReferenceFor(TestCart $cart, ?string $payment_id = null): string
    {
        $provider = new Worldline;

        $payableProperty = new ReflectionProperty($provider, 'payableModel');
        $payableProperty->setAccessible(true);
        $payableProperty->setValue($provider, $cart);

        $paymentProperty = new ReflectionProperty($provider, 'payment');
        $paymentProperty->setAccessible(true);
        $paymentProperty->setValue($provider, $payment_id ? Payment::find($payment_id) : $this->createPaymentRecord());

        $method = new ReflectionMethod($provider, 'merchantReference');
        $method->setAccessible(true);

        return $method->invoke($provider);
    }

    #[Test]
    public function it_rejects_a_webhook_signed_with_the_wrong_secret(): void
    {
        $payment = $this->createPaymentRecord();

        $request = $this->signedWebhookRequest($payment->id, 'not-the-secret', 'test-key-id');

        $this->expectException(SignatureValidationException::class);

        (new Worldline)->resolvePaymentFromWebhook($request);
    }

    protected function createPaymentRecord(array $attributes = []): Payment
    {
        $provider = PaymentProvider::firstOrCreate([
            'slug' => 'worldline',
        ], [
            'name' => 'Worldline',
            'type' => PaymentProvider::PROVIDER_WORLDLINE,
            'active' => true,
        ]);

        $type = PaymentType::firstOrCreate([
            'slug' => 'ideal',
        ], [
            'payment_provider_id' => $provider->id,
            'name' => 'iDEAL',
            'active' => true,
        ]);

        return Payment::create(array_merge([
            'payable_type' => 'cart',
            'payable_id' => 1,
            'payment_provider_id' => $provider->id,
            'payment_type_id' => $type->id,
            'simple_checkout' => false,
            'total_amount' => 1000,
            'paid_amount' => 0,
            'started' => now(),
            'start_ip' => '127.0.0.1',
        ], $attributes));
    }

    protected function signedWebhookRequest(string $merchantReference, string $secret, string $keyId, string $paymentId = 'pay_1'): Request
    {
        $body = json_encode([
            'apiVersion' => 'v1',
            'id' => 'evt_1',
            'type' => 'payment.captured',
            'payment' => [
                'id' => $paymentId,
                'paymentOutput' => [
                    'references' => ['merchantReference' => $merchantReference],
                ],
            ],
        ]);

        $signature = base64_encode(hash_hmac('sha256', $body, $secret, true));

        $request = Request::create('/worldline/webhook', 'POST', [], [], [], [], $body);
        $request->headers->set('X-GCS-Signature', $signature);
        $request->headers->set('X-GCS-KeyId', $keyId);

        return $request;
    }
}
