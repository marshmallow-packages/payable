<?php

namespace Marshmallow\Payable\Providers;

use Exception;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Marshmallow\Payable\Models\Payment;
use Mollie\Laravel\Wrappers\MollieApiWrapper;
use Mollie\Laravel\Facades\Mollie as MollieApi;
use Marshmallow\Payable\Resources\PaymentRefund;
use Mollie\Api\Resources\Payment as MolliePayment;
use Marshmallow\Payable\Http\Responses\PaymentStatusResponse;
use Marshmallow\Payable\Providers\Contracts\PaymentProviderContract;

class Mollie extends Provider implements PaymentProviderContract
{
    protected function getClient($api_key = null): MollieApiWrapper
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
            'orderNumber' => $this->getPayableIdentifier(),
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
            'consumerDateOfBirth' => $this->payableModel->getConsumerDateOfBirth(),
            // 'description' => $this->getPayableDescription(),
            'redirectUrl' => $this->redirectUrl(),
            'webhookUrl' => $this->webhookUrl(),
            'locale' => config('payable.locale'),
        ];

        $this->payableModel->items->each(function ($item) use (&$payload) {

            $payload['lines'][] = [
                'type' => 'physical', //physical|discount|digital|shipping_fee|store_credit|gift_card|surcharge
                'name' => $item->description,
                'quantity' => $item->quantity,
                'discountAmount' => [
                    'currency' => $this->getCurrencyIso4217Code(),
                    'value' => $this->formatCentToDecimalString(
                        0
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
                        $item->getTotalAmount()
                    ),
                ],
                'vatRate' => (string) $item->vatrate->rate,
                'vatAmount' =>
                [
                    'currency' => $this->getCurrencyIso4217Code(),
                    'value' => $this->formatCentToDecimalString(
                        $item->getTotalVatAmount()
                    ),
                ],
            ];
        });

        return $api->orders->create($payload);
    }

    public function refund(Payment $payment, int $amount)
    {
        $api = $this->getClient();

        if (Str::of($payment->provider_id)->startsWith('tr_')) {
            /** Refund payments */
            $mollie_payment = $api->payments->get($payment->provider_id);
            $result = $api->payments->refund($mollie_payment, [
                'amount' => [
                    'currency' => $this->getCurrencyIso4217Code(),
                    'value' => $this->formatCentToDecimalString($amount),
                ],
            ]);
        } else {
            /** Refund orders */
            $mollie_payment = $api->orders->get($payment->provider_id);
            $result = $api->orders->refund($mollie_payment, [
                'amount' => [
                    'currency' => $this->getCurrencyIso4217Code(),
                    'value' => $this->formatCentToDecimalString($amount),
                ],
            ]);
        }

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
        if (config('payable.use_order_payments') === true) {
            return MollieApi::api()->orders->get($payment->provider_id);
        }

        return MollieApi::api()->payments->get($payment->provider_id);
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
