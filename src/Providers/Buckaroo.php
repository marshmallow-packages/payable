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
use Marshmallow\Payable\Traits\BuckarooSubscriptions;
use Marshmallow\Payable\Services\Buckaroo\BuckarooApi;
use Marshmallow\Payable\Http\Responses\PaymentStatusResponse;
use Marshmallow\Payable\Providers\Contracts\PaymentProviderContract;

class Buckaroo extends Provider implements PaymentProviderContract
{
    protected function getClient($testMode = false): BuckarooApi
    {
        return new BuckarooApi(
            testMode: $testMode
        );
    }

    public function createPayment()
    {
        $api = $this->getClient($this->testPayment);

        $extra_data_method = $this->extraPaymentDataCallback;
        $extra_data = $extra_data_method();

        return $api->createPayment([
            'Currency' => $this->getCurrencyIso4217Code(),
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
                        'Name' => $extra_data->service,
                        'Parameters' => [
                            [
                                'Name' => 'issuer',
                                'Value' => $extra_data->issuer,
                            ]
                        ],
                    ]
                ],
            ],
        ]);
    }

    public function createRecurringPayment()
    {
        if (!in_array(BuckarooSubscriptions::class, class_uses($this->payableModel), true)) {
            throw new Exception(get_class($this->payableModel) . ' should implement the BuckarooSubscriptions trait.', 1);
        }

        $api = $this->getClient($this->testPayment);

        $start_date = $this->payableModel->getBuckarooSubscriptionStartDate();
        $rate_plan_code = $this->payableModel->getBuckarooSubscriptionRatePlanCode();
        $configuration_code = $this->payableModel->getBuckarooSubscriptionConfigurationCode();
        $debtor_code = $this->payableModel->getBuckarooSubscriptionDebtorCode();

        $subscription_model = $this->payableModel->buckarooSubscriptions()->create([
            'start_date' => $start_date,
            'configuration_code' => $configuration_code,
            'rate_plan_code' => $rate_plan_code,
            'debtor_code' => $debtor_code,
        ]);

        $this->updateOrCreateBuckarooDebtor($api);

        $response = $api->createPayment(
            endpoint: 'DataRequest',
            payment_data: [
                'Currency' => $this->getCurrencyIso4217Code(),
                'ReturnURL' => $this->redirectUrl(),
                'ReturnURLCancel' => $this->redirectUrl(),
                'ReturnURLError' => $this->redirectUrl(),
                'ReturnURLReject' => $this->redirectUrl(),
                'PushURL' => $this->webhookUrl(),
                'PushURLFailure' => $this->webhookUrl(),
                'Services' => [
                    'ServiceList' => [
                        [
                            'Name' => 'Subscriptions',
                            'Action' => 'CreateSubscription',
                            'Parameters' => [
                                [
                                    'Name' => 'StartDate',
                                    'GroupType' => 'Addrateplan',
                                    'GroupID' => '',
                                    'Value' => $start_date->format('d-m-Y'),
                                ],
                                [
                                    'Name' => 'RatePlanCode',
                                    'GroupType' => 'Addrateplan',
                                    'GroupID' => '',
                                    'Value' => $rate_plan_code,
                                ],
                                [
                                    'Name' => 'Code',
                                    'GroupType' => 'Debtor',
                                    'GroupID' => '',
                                    'Value' => $debtor_code,
                                ],
                                [
                                    'Name' => 'ConfigurationCode',
                                    'Value' => $configuration_code,
                                ]
                            ],
                        ]
                    ],
                ],
            ],
        );

        $subscription_guid = collect(Arr::get($response, 'Services.0.Parameters'))->mapWithKeys(function ($values) {
            return [$values['Name'] => $values['Value']];
        })->toArray();

        $subscription_model->update([
            'subscription_guid' => Arr::get($subscription_guid, 'SubscriptionGuid'),
            'subscriptions' => Arr::get($response, 'Services.0.Parameters'),
            'services' => Arr::get($response, 'Services'),
            'custom_parameters' => Arr::get($response, 'CustomParameters'),
            'additional_parameters' => Arr::get($response, 'AdditionalParameters'),
            'request_errors' => Arr::get($response, 'RequestErrors'),
            'is_test' => Arr::get($response, 'IsTest'),
        ]);

        return $response;
    }

    public function stopSubscription(string $subscription_guid, $test_mode = false)
    {
        $api = $this->getClient($test_mode);
        $response = $api->createPayment(
            endpoint: 'DataRequest',
            payment_data: [
                'Services' => [
                    'ServiceList' => [
                        [
                            'Name' => 'Subscriptions',
                            'Action' => 'StopSubscription',
                            'Parameters' => [
                                [
                                    'Name' => 'SubscriptionGuid',
                                    'Value' => $subscription_guid,
                                ],
                            ],
                        ]
                    ],
                ],
            ],
        );

        return $response;
    }

    public function updateOrCreateBuckarooDebtor(BuckarooApi $api)
    {
        $api->createPayment(
            endpoint: 'DataRequest',
            payment_data: [
                'Services' => [
                    'ServiceList' => [
                        [
                            'Name' => 'CreditManagement3',
                            'Action' => 'AddOrUpdateDebtor',
                            'Parameters' => [
                                [
                                    'Name' => 'Code',
                                    'GroupType' => 'Debtor',
                                    'GroupID' => '',
                                    'Value' => $this->payableModel->getBuckarooSubscriptionDebtorCode(),
                                ],
                                [
                                    'Name' => 'Culture',
                                    'GroupType' => 'Person',
                                    'GroupID' => '',
                                    'Value' => $this->payableModel->getBuckarooSubscriptionLocale(),
                                ],
                                [
                                    'Name' => 'FirstName',
                                    'GroupType' => 'Person',
                                    'GroupID' => '',
                                    'Value' => $this->payableModel->getFirstName(),
                                ],
                                [
                                    'Name' => 'LastName',
                                    'GroupType' => 'Person',
                                    'GroupID' => '',
                                    'Value' => $this->payableModel->getLastName(),
                                ],
                                [
                                    'Name' => 'Email',
                                    'GroupType' => 'Email',
                                    'GroupID' => '',
                                    'Value' => $this->payableModel->getEmailAddress(),
                                ],
                            ],
                        ]
                    ],
                ],
            ],
        );
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
        $paid_amount = intval(floatval(Arr::get($payment, 'AmountDebit')) * 100);
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
