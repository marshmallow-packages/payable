<?php

namespace Marshmallow\Payable\Tests\Unit;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Marshmallow\Payable\Tests\TestCase;
use Marshmallow\Payable\Models\Payment;
use Marshmallow\Payable\Providers\Buckaroo;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Marshmallow\Payable\Http\Responses\PaymentStatusResponse;

/**
 * The Buckaroo webhook handler used to start with a dd(), so every push
 * dumped and died. It also checked a request field Buckaroo never sends
 * (`id`); the push carries the transaction key instead.
 */
class BuckarooWebhookTest extends TestCase
{
    #[Test]
    #[DataProvider('pushes')]
    public function it_accepts_a_push_whose_transaction_key_matches_the_payment(array $input): void
    {
        $response = $this->provider()->handleWebhookNotification(
            $this->payment('TX-123'),
            Request::create('/payment/webhook/1', 'POST', $input),
        );

        $this->assertSame(Payment::STATUS_PAID, $response->getStatus());
    }

    public static function pushes(): array
    {
        return [
            'json push' => [['Transaction' => ['Key' => 'TX-123']]],
            'form push' => [['brq_transactions' => 'TX-123']],
            'upper-cased form push' => [['BRQ_TRANSACTIONS' => 'TX-123']],
            'push without a key' => [['Transaction' => ['Status' => ['Code' => ['Code' => 190]]]]],
        ];
    }

    #[Test]
    public function it_refuses_a_push_for_another_transaction(): void
    {
        $this->expectException(HttpException::class);

        $this->provider()->handleWebhookNotification(
            $this->payment('TX-123'),
            Request::create('/payment/webhook/1', 'POST', ['Transaction' => ['Key' => 'TX-999']]),
        );
    }

    #[Test]
    public function it_maps_a_merchant_cancellation_to_canceled(): void
    {
        $this->assertSame(Payment::STATUS_CANCELED, (new Buckaroo)->convertStatus(890));
        $this->assertSame(Payment::STATUS_CANCELED, (new Buckaroo)->convertStatus(891));
        $this->assertSame(Payment::STATUS_PAID, (new Buckaroo)->convertStatus(190));
    }

    /**
     * A provider that answers "paid" without asking Buckaroo's API.
     */
    protected function provider(): Buckaroo
    {
        return new class extends Buckaroo
        {
            public function handleResponse(Payment $payment): PaymentStatusResponse
            {
                return new PaymentStatusResponse(Payment::STATUS_PAID, 1000);
            }
        };
    }

    protected function payment(string $providerId): Payment
    {
        return new Payment(['provider_id' => $providerId]);
    }
}
