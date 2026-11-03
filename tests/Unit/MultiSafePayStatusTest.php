<?php

namespace Marshmallow\Payable\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use Marshmallow\Payable\Models\Payment;
use PHPUnit\Framework\Attributes\Test;
use Marshmallow\Payable\Tests\TestCase;
use Psr\Http\Client\ClientInterface;
use Marshmallow\Payable\Providers\MultiSafePay;

class MultiSafePayStatusTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        config(['payable.multisafepay.key' => 'test-key']);
    }

    /**
     * A webhook read the order once for the status and once more for the
     * consumer details. That second call occasionally came back
     * "Unauthorized" and failed a webhook whose status had already been
     * stored. The mock holds exactly one response, so a second call fails.
     */
    #[Test]
    public function a_webhook_reads_the_order_from_multisafepay_once(): void
    {
        $mock = new MockHandler([$this->orderResponse('completed')]);
        $provider = $this->multiSafePayWith($mock);
        $payment = new Payment(['provider_id' => 'order-1']);

        $response = $provider->handleResponse($payment);

        $this->assertSame(Payment::STATUS_PAID, $response->getStatus());
        $this->assertSame('J. Jansen', $provider->getConsumerName($payment));
        $this->assertSame('NL02ABNA0123456789', $provider->getConsumerAccount($payment));
        $this->assertSame('IDEAL', $provider->getPaymentTypeName($payment));
        $this->assertSame(0, $mock->count());
    }

    protected function multiSafePayWith(MockHandler $mock): MultiSafePay
    {
        return new class(new Client(['handler' => HandlerStack::create($mock)])) extends MultiSafePay
        {
            public function __construct(private ClientInterface $client) {}

            protected function httpClient(): ClientInterface
            {
                return $this->client;
            }
        };
    }

    protected function orderResponse(string $status): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'success' => true,
            'data' => [
                'order_id' => 'order-1',
                'status' => $status,
                'amount' => 1000,
                'currency' => 'EUR',
                'modified' => '2026-11-03T10:37:09',
                'payment_details' => [
                    'account_holder_name' => 'J. Jansen',
                    'account_iban' => 'NL02ABNA0123456789',
                    'account_bic' => 'ABNANL2A',
                    'type' => 'IDEAL',
                ],
            ],
        ]));
    }
}
