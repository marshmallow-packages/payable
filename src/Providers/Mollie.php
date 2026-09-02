<?php

namespace Marshmallow\Payable\Providers;

use Exception;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Marshmallow\Payable\Models\Payment;
use Mollie\Laravel\Facades\Mollie as MollieApi;
use Marshmallow\Payable\Resources\PaymentRefund;
use Marshmallow\Payable\Http\Responses\PaymentStatusResponse;
use Marshmallow\Payable\Providers\Contracts\PaymentProviderContract;

/**
 * Mollie payment provider.
 *
 * As of mollie/laravel-mollie v4 (mollie-api-php v3) the Orders API has been
 * removed by Mollie. Everything is handled through the Payments API:
 *
 *   - Orders with line items        -> payments->create() with a "lines" array
 *   - Order shipments / shipAll()   -> payment captures (amount based)
 *   - Order refunds / refundAll()   -> payments->refund() (amount based)
 *
 * See https://docs.mollie.com/docs/migrating-from-orders-to-payments.
 *
 * Backwards compatibility note: the v3 SDK dropped its Orders endpoints, but
 * the Orders API itself is still served, so a legacy order id (ord_...) can
 * still be READ over plain HTTP - verified against a live order. Status reads
 * therefore keep working for payments created before the migration. Writing
 * operations (refund, shipment) are not implemented for legacy orders and
 * still throw.
 */
class Mollie extends Provider implements PaymentProviderContract
{
    protected function getClient($api_key = null)
    {
        $api = MollieApi::api();
        if ($api_key) {
            $api->setApiKey($api_key);
        }

        return $api;
    }

    public function createPayment($api_key = null)
    {
        if (config('payable.use_order_payments') === true) {
            return $this->createOrder($api_key);
        }

        $api = $this->getClient($api_key);

        return $api->payments->create([
            'amount' => [
                'currency' => $this->getCurrencyIso4217Code(),
                'value' => $this->formatCentToDecimalString(
                    $this->getPayableAmount()
                ),
            ],
            'description' => $this->getPayableDescription(),
            'redirectUrl' => $this->redirectUrl(),
            'webhookUrl' => $this->webhookUrl(),
            'locale' => config('payable.locale'),
        ]);
    }

    /**
     * Create a Mollie payment that carries order line details.
     *
     * This replaces the old Orders API ($api->orders->create()). The line and
     * address payloads keep the same shape Mollie uses, with two field renames
     * required by the Payments API: the order "orderNumber" moves to metadata
     * and each line "name" becomes "description".
     */
    public function createOrder($api_key = null)
    {
        $api = $this->getClient($api_key);

        $payload = [
            'amount' => [
                'currency' => $this->getCurrencyIso4217Code(),
                'value' => $this->formatCentToDecimalString(
                    $this->getPayableAmount()
                ),
            ],
            'description' => $this->getPayableDescription(),
            'metadata' => [
                'order_number' => $this->getPayableIdentifier(),
            ],
            'lines' => [],
            'billingAddress' => [
                'organizationName' => $this->payableModel->getBillingOrganizationName(),
                'title' => $this->payableModel->getBillingTitle(),
                'givenName' => $this->payableModel->getBillingGivenName(), //required
                'familyName' => $this->payableModel->getBillingFamilyName(), //required
                'email' => $this->payableModel->getBillingEmailaddress(), //required
                'phone' => $this->payableModel->getBillingPhonenumber(),
                'streetAndNumber' => $this->payableModel->getBillingStreetAndNumber(), //required
                'streetAdditional' => $this->payableModel->getBillingStreetAdditional(),
                'postalCode' => $this->payableModel->getBillingPostalCode(),
                'city' => $this->payableModel->getBillingCity(), //required
                'region' => $this->payableModel->getBillingRegion(),
                'country' => $this->payableModel->getBillingCountry(), //required
            ],
            'shippingAddress' => [
                'organizationName' => $this->payableModel->getShippingOrganizationName(),
                'title' => $this->payableModel->getShippingTitle(),
                'givenName' => $this->payableModel->getShippingGivenName(), //required
                'familyName' => $this->payableModel->getShippingFamilyName(), //required
                'email' => $this->payableModel->getShippingEmailaddress(), //required
                'phone' => $this->payableModel->getShippingPhonenumber(),
                'streetAndNumber' => $this->payableModel->getShippingStreetAndNumber(), //required
                'streetAdditional' => $this->payableModel->getShippingStreetAdditional(),
                'postalCode' => $this->payableModel->getShippingPostalCode(),
                'city' => $this->payableModel->getShippingCity(), //required
                'region' => $this->payableModel->getShippingRegion(),
                'country' => $this->payableModel->getShippingCountry(), //required
            ],
            'redirectUrl' => $this->redirectUrl(),
            'webhookUrl' => $this->webhookUrl(),
            'locale' => config('payable.locale'),
        ];

        // Pay-later methods (klarna, billie, in3, riverty) only reach the
        // "authorized" state — instead of being captured immediately — when
        // manual capture is requested. Enable this through config when you rely
        // on the authorize -> ship/capture flow below.
        if ($capture_mode = config('payable.mollie.capture_mode')) {
            $payload['captureMode'] = $capture_mode;
        }

        $this->payableModel->items->each(function ($item) use (&$payload) {
            $type = match ($item->type) {
                'DISCOUNT' => 'discount',
                'SHIPPING' => 'shipping_fee',
                'PRODUCT' => 'physical',
                default => 'physical',
            };

            $discount_amount = 0;
            $total_amount = $item->getTotalAmount();
            $vat_amount = $item->getTotalVatAmount();

            $has_price_difference = ($item->discount_including_vat !== $item->price_including_vat);

            if ($has_price_difference && $item->discount_including_vat && $item->discount_including_vat > 0) {
                $discount_amount = ($item->price_including_vat - $item->discount_including_vat) * $item->quantity;
                $total_amount = $total_amount - $discount_amount;
                $vat_amount = ($item->discount_vat_amount * $item->quantity);
            }

            $payload['lines'][] = [
                'type' => $type, //physical|discount|digital|shipping_fee|store_credit|gift_card|surcharge
                'description' => $item->description,
                'quantity' => $item->quantity,
                'discountAmount' => [
                    'currency' => $this->getCurrencyIso4217Code(),
                    'value' => $this->formatCentToDecimalString(
                        $discount_amount
                    ),
                ],
                'unitPrice' => [
                    'currency' => $this->getCurrencyIso4217Code(),
                    'value' => $this->formatCentToDecimalString(
                        $item->display_price
                    ),
                ],
                'totalAmount' => [
                    'currency' => $this->getCurrencyIso4217Code(),
                    'value' => $this->formatCentToDecimalString(
                        $total_amount
                    ),
                ],
                'vatRate' => (string) $item->vatrate->rate,
                'vatAmount' => [
                    'currency' => $this->getCurrencyIso4217Code(),
                    'value' => $this->formatCentToDecimalString(
                        $vat_amount
                    ),
                ],
            ];
        });

        return $api->payments->create($payload);
    }

    /**
     * Capture (a part of) an authorized payment.
     *
     * Replaces the Orders API shipment flow. Captures are amount based — the
     * Payments API has no per-line shipments — so pass the amount in cents you
     * want to capture, or null to capture the full authorized amount.
     *
     * @deprecated use createShipmentWithTracking instead
     */
    public function createShipment(Payment $payment, ?int $amount = null, $api_key = null)
    {
        return $this->createShipmentWithTracking($payment, $amount, [], $api_key);
    }

    /**
     * Capture (a part of) an authorized payment, optionally storing tracking
     * details in the capture metadata (Mollie no longer tracks shipments).
     */
    public function createShipmentWithTracking(Payment $payment, ?int $amount = null, array $tracking = [], $api_key = null)
    {
        $this->guardAgainstLegacyOrder($payment);

        $api = $this->getClient($api_key);

        $options = [
            'description' => $this->getPayableDescription(),
        ];

        if (!is_null($amount)) {
            $options['amount'] = [
                'currency' => $this->getCurrencyIso4217Code(),
                'value' => $this->formatCentToDecimalString($amount),
            ];
        }

        if (!empty($tracking)) {
            $options['metadata'] = ['tracking' => $tracking];
        }

        return $api->paymentCaptures->createForId($payment->provider_id, $options);
    }

    protected function isOrder($payment_id)
    {
        return Str::of($payment_id)->startsWith('ord_');
    }

    /**
     * Guards the operations that WRITE to a legacy order - refunds and
     * shipments - which this package does not implement against the Orders
     * API. Reading a legacy order still works, see getPaymentStatus().
     */
    protected function guardAgainstLegacyOrder(Payment $payment): void
    {
        if ($this->isOrder($payment->provider_id)) {
            throw new Exception(
                "Mollie order {$payment->provider_id} predates the Payments API migration and cannot be refunded or shipped through this package; handle it in the Mollie dashboard. See https://docs.mollie.com/docs/migrating-from-orders-to-payments."
            );
        }
    }

    public function refund(Payment $payment, int $amount, $api_key = null)
    {
        $this->guardAgainstLegacyOrder($payment);

        $api = $this->getClient($api_key);

        $mollie_payment = $api->payments->get($payment->provider_id);

        $result = $api->payments->refund($mollie_payment, [
            'amount' => [
                'currency' => $this->getCurrencyIso4217Code(),
                'value' => $this->formatCentToDecimalString($amount),
            ],
        ]);

        return new PaymentRefund(
            provider_id: $result->id,
            status: $result->status,
        );
    }

    public function getPaymentId()
    {
        return $this->provider_payment_object->id;
    }

    public function getPaymentUrl(): string
    {
        return $this->provider_payment_object->getCheckoutUrl();
    }

    public function handleReturnNotification(Payment $payment, Request $request): PaymentStatusResponse
    {
        return $this->handleResponse($payment);
    }

    public function handleWebhookNotification(Payment $payment, Request $request): PaymentStatusResponse
    {
        $paymentId = $request->input('id');

        if ($paymentId != $payment->provider_id) {
            abort(403);
        }

        return $this->handleResponse($payment);
    }

    public function convertStatus($status): string
    {
        switch ($status) {
            case 'open':
                return Payment::STATUS_OPEN;
                break;

            case 'pending':
                return Payment::STATUS_PENDING;
                break;

            case 'paid':
            case 'authorized':
                return Payment::STATUS_PAID;
                break;

            case 'failed':
                return Payment::STATUS_FAILED;
                break;

            case 'canceled':
                return Payment::STATUS_CANCELED;
                break;

            case 'expired':
                return Payment::STATUS_EXPIRED;
                break;

            default:
                throw new Exception("Unknown payment status {$status}");
                break;
        }
    }

    public function getPaymentStatus(Payment $payment)
    {
        if ($this->isOrder($payment->provider_id)) {
            return $this->getLegacyOrderPaymentStatus($payment);
        }

        return $this->getClient()->payments->get($payment->provider_id);
    }

    /**
     * Read a pre-migration order (ord_...) and return the payment behind it.
     *
     * The v3 SDK removed its Orders endpoints, but the API still serves them,
     * so one authenticated GET recovers what the webhook needs. Nothing is
     * written here: a failure throws, which leaves the webhook unacknowledged
     * so Mollie retries, rather than recording a status we are not sure of.
     */
    protected function getLegacyOrderPaymentStatus(Payment $payment, $api_key = null): object
    {
        $order = $this->fetchLegacyOrder($payment, $api_key);

        $payments = collect($order->_embedded->payments ?? []);

        // An order can carry several attempts. A successful one is the truth
        // about the order regardless of how many failures came before it, so
        // never let an earlier attempt overwrite it.
        $settled = $payments->first(
            fn ($attempt) => in_array($attempt->status ?? null, ['paid', 'authorized'], true)
        );

        if ($settled) {
            return $settled;
        }

        if ($payments->isNotEmpty()) {
            return $payments->sortBy(fn ($attempt) => $attempt->createdAt ?? '')->last();
        }

        // No attempt was ever created; the order's own status is all there is.
        return $order;
    }

    protected function fetchLegacyOrder(Payment $payment, $api_key = null): object
    {
        $key = $api_key ?: config('mollie.key');

        if (blank($key)) {
            throw new Exception(
                "Cannot read Mollie order {$payment->provider_id}: no API key configured for this domain."
            );
        }

        $response = Http::withToken($key)
            ->timeout(15)
            ->acceptJson()
            ->get("https://api.mollie.com/v2/orders/{$payment->provider_id}", ['embed' => 'payments']);

        if (! $response->successful()) {
            throw new Exception(
                "Could not read Mollie order {$payment->provider_id}: HTTP {$response->status()}."
            );
        }

        return $response->object();
    }

    public function handleResponse(Payment $payment): PaymentStatusResponse
    {
        $payment = $this->getPaymentStatus($payment);
        $status = $this->convertStatus($payment->status);
        $paid_amount = intval(floatval($payment->amount->value) * 100);

        return new PaymentStatusResponse($status, $paid_amount);
    }

    public function formatCentToDecimalString($amount): string
    {
        /**
         * Mollie says;
         * You must send the correct number of decimals, thus we enforce the use of strings
         */
        return number_format($amount / 100, 2, '.', '');
    }

    public function getCanceledAt(Payment $payment): ?Carbon
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);

        return Carbon::parse($info->canceledAt);
    }

    public function getExpiresAt(Payment $payment): ?Carbon
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);

        return Carbon::parse($info->expiresAt);
    }

    public function getFailedAt(Payment $payment): ?Carbon
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);

        return Carbon::parse($info->failedAt);
    }

    public function getPaidAt(Payment $payment): ?Carbon
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);

        return Carbon::parse($info->paidAt);
    }

    protected function getPaymentDetail(Payment $payment, string $column): ?string
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        if (!isset($info->details)) {
            return null;
        }
        if (!isset($info->details->{$column})) {
            return null;
        }

        return $info->details->{$column};
    }

    public function getConsumerName(Payment $payment): ?string
    {
        return $this->getPaymentDetail($payment, 'consumerName');
    }

    public function getConsumerAccount(Payment $payment): ?string
    {
        return $this->getPaymentDetail($payment, 'consumerAccount');
    }

    public function getConsumerBic(Payment $payment): ?string
    {
        return $this->getPaymentDetail($payment, 'consumerBic');
    }

    public function getPaymentTypeName(Payment $payment): ?string
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);

        return $info->method;
    }
}