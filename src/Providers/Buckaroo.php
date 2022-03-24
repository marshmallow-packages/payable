<?php

namespace Marshmallow\Payable\Providers;

use Exception;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Marshmallow\Payable\Models\Payment;
use Mollie\Laravel\Wrappers\MollieApiWrapper;
use Mollie\Laravel\Facades\Mollie as MollieApi;
use Marshmallow\Payable\Resources\PaymentRefund;
use Mollie\Api\Resources\Payment as MolliePayment;
use Marshmallow\Payable\Services\Buckaroo\BuckarooApi;
use Marshmallow\Payable\Http\Responses\PaymentStatusResponse;
use Marshmallow\Payable\Providers\Contracts\PaymentProviderContract;

class Buckaroo extends Provider implements PaymentProviderContract
{
    protected function getClient(): BuckarooApi
    {
        return new BuckarooApi;
    }

    public function createPayment()
    {
        $api = $this->getClient();

        return $api->createPayment([
            'Currency' => "EUR",
            'AmountDebit' => $this->getPayableAmount() / 100,
            'Invoice' => $this->getPayableDescription(),
            'ReturnURL' => $this->redirectUrl(),
            'ReturnURLCancel' => $this->redirectUrl(),
            'ReturnURLError' => $this->redirectUrl(),
            'ReturnURLReject' => $this->redirectUrl(),
            'PushURL' => $this->webhookUrl(),
            'PushURLFailure' => $this->webhookUrl(),
            'Services' => [
                'ServiceList' => [
                    [
                        'Action' => 'Pay',
                        'Name' => 'ideal',
                        'Parameters' => [
                            [
                                'Name' => 'issuer',
                                'Value' => 'ABNANL2A',
                            ]
                        ],
                    ]
                ],
            ],
        ]);
    }

    public function refund(Payment $payment, int $amount)
    {
        $api = $this->getClient();

        $result = $api->refund(
            transactionKey: $payment->provider_id,
            amount: $amount / 100,
        );

        return new PaymentRefund(
            provider_id: Arr::get($result, 'Key'),
            status: Arr::get($result, 'Status.Code.Code'),
        );
    }

    public function getPaymentId()
    {
        return Arr::get($this->provider_payment_object, 'Key');
    }

    public function getPaymentUrl(): string
    {
        return Arr::get($this->provider_payment_object, 'RequiredAction.RedirectURL');
    }

    public function handleReturnNotification(Payment $payment, Request $request): PaymentStatusResponse
    {
        return $this->handleResponse($payment);
    }

    public function handleWebhookNotification(Payment $payment, Request $request): PaymentStatusResponse
    {
        dd(__LINE__);
        $paymentId = $request->input('id');

        if ($paymentId != $payment->provider_id) {
            abort(403);
        }

        return $this->handleResponse($payment);
    }

    public function convertStatus($status): string
    {
        switch ($status) {

            case 190: // Success (190): The transaction has succeeded and the payment has been received/approved.
                return Payment::STATUS_PAID;
                break;

            case 490: // Failed (490): The transaction has failed.
            case 491: // Validation Failure (491): The transaction request contained errors and could not be processed correctly
            case 492: // Technical Failure (492): Some technical failure prevented the completion of the transactions
            case 690: // Rejected (690): The transaction has been rejected by the (third party) payment provider.
                return Payment::STATUS_FAILED;
                break;

            case 890: // Cancelled By User (890): The transaction was cancelled by the customer.
            case 890: // Cancelled By Merchant (891): The merchant cancelled the transaction.
                return Payment::STATUS_CANCELED;
                break;

            case 790: // Pending Input (790): The transaction is on hold while the payment engine is waiting on further input from the consumer.
            case 791: // Pending Processing (791): The transaction is being processed.
            case 792: // Awaiting Consumer (792): The Payment Engine is waiting for the consumer to return from a third party website, needed to complete the transaction.
                return Payment::STATUS_PENDING;
                break;

            default:
                throw new Exception("Unknown payment status {$status}");
                break;
        }
    }

    public function getPaymentStatus(Payment $payment)
    {
        $api = $this->getClient();
        return $api->getPaymentStatus(
            transactionKey: $payment->provider_id
        );
    }

    public function handleResponse(Payment $payment): PaymentStatusResponse
    {
        $payment = $this->getPaymentStatus($payment);
        $status = $this->convertStatus(
            Arr::get($payment, 'Status.Code.Code')
        );
        $paid_amount = intval(floatval(Arr::get($payment, 'AmountDebit')) * 100) ;
        return new PaymentStatusResponse($status, $paid_amount);
    }

    public function getCanceledAt(Payment $payment): ?Carbon
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        return Carbon::parse(
            Arr::get($info, 'Status.DateTime')
        );
    }

    public function getExpiresAt(Payment $payment): ?Carbon
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        return Carbon::parse(
            Arr::get($info, 'Status.DateTime')
        );
    }

    public function getFailedAt(Payment $payment): ?Carbon
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        return Carbon::parse(
            Arr::get($info, 'Status.DateTime')
        );
    }

    public function getPaidAt(Payment $payment): ?Carbon
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        return Carbon::parse(
            Arr::get($info, 'Status.DateTime')
        );
    }

    public function getConsumerName(Payment $payment): ?string
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        return Arr::get($info, 'CustomerName');
    }

    public function getConsumerAccount(Payment $payment): ?string
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        foreach (Arr::get($info, 'Services.0.Parameters') as $parameters) {
            if (Arr::get($parameters, 'Name') == 'consumerIBAN') {
                return Arr::get($parameters, 'Value');
            }
        }

        return null;
    }

    public function getConsumerBic(Payment $payment): ?string
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        foreach (Arr::get($info, 'Services.0.Parameters') as $parameters) {
            if (Arr::get($parameters, 'Name') == 'consumerBIC') {
                return Arr::get($parameters, 'Value');
            }
        }

        return null;
    }

    public function getPaymentTypeName(Payment $payment): ?string
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        return Arr::get($info, 'ServiceCode');
    }
}
