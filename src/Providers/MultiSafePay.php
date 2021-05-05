<?php

namespace Marshmallow\Payable\Providers;

use Exception;
use Carbon\Carbon;
use Illuminate\Http\Request;
use MultiSafepay\ValueObject\Money;
use Marshmallow\Payable\Models\Payment;
use Marshmallow\Payable\Facades\Payable;
use MultiSafepay\ValueObject\Customer\Address;
use MultiSafepay\ValueObject\Customer\Country;
use Mollie\Laravel\Facades\Mollie as MollieApi;
use MultiSafepay\Api\Transactions\OrderRequest;
use MultiSafepay\ValueObject\Customer\PhoneNumber;
use MultiSafepay\ValueObject\Customer\EmailAddress;
use MultiSafepay\Api\Transactions\TransactionResponse;
use Marshmallow\Payable\Http\Responses\PaymentStatusResponse;
use Marshmallow\Payable\Providers\Contracts\PaymentProviderContract;
use MultiSafepay\Api\Transactions\OrderRequest\Arguments\PluginDetails;
use MultiSafepay\Api\Transactions\OrderRequest\Arguments\PaymentOptions;
use MultiSafepay\Api\Transactions\OrderRequest\Arguments\CustomerDetails;

class MultiSafePay extends Provider implements PaymentProviderContract
{
    protected function getClient()
    {
        $test_payment = config('payable.test_payments');
        $is_production = (!$test_payment);
        return new \MultiSafepay\Sdk(env('MULTI_SAFE_PAY_KEY'), $is_production);
    }

    public function createPayment($api_key = null)
    {
        $multiSafepaySdk = $this->getClient();

        $description = $this->getPayableDescription();
        $amount = new Money($this->getPayableAmount(), $this->getCurrencyIso4217Code());

        $paymentOptions = (new PaymentOptions)
            ->addNotificationUrl($this->webhookUrl())
            ->addRedirectUrl($this->redirectUrl())
            ->addCancelUrl($this->redirectUrl())
            ->addCloseWindow(true);

        $pluginDetails = (new PluginDetails)
            ->addApplicationName(config('app.name'))
            ->addApplicationVersion('1.0.0');

        $orderRequest = (new OrderRequest)
            ->addType('redirect')
            ->addOrderId($this->getPayableIdentifier())
            ->addDescriptionText($description)
            ->addMoney($amount)
            ->addPluginDetails($pluginDetails)
            ->addPaymentOptions($paymentOptions);

        return $multiSafepaySdk->getTransactionManager()->create($orderRequest);
    }

    public function getPaymentId()
    {
        return $this->provider_payment_object->getOrderId();
    }

    public function getPaymentUrl(): string
    {
        return $this->provider_payment_object->getPaymentUrl();
    }

    public function handleReturnNotification(Payment $payment, Request $request): PaymentStatusResponse
    {
        return $this->handleResponse($payment);
    }

    public function handleWebhookNotification(Payment $payment, Request $request): PaymentStatusResponse
    {
        $paymentId = $request->input('transactionid');

        if ($paymentId != $payment->provider_id) {
            abort(403);
        }

        return $this->handleResponse($payment);
    }

    public function convertStatus($status): string
    {
        /**
         * TO DO: handle status `shipped`.
         */
        switch ($status) {
            case 'initialized':
            case 'void':
            case 'reserved':
                return Payment::STATUS_OPEN;
                break;

            case 'completed':
                return Payment::STATUS_PAID;
                break;

            case 'declined':
            case 'uncleared':
                return Payment::STATUS_FAILED;
                break;

            case 'cancelled':
                return Payment::STATUS_CANCELED;
                break;

            case 'expired':
                return Payment::STATUS_EXPIRED;
                break;

            case 'refunded':
            case 'partial_refunded':
            case 'chargeback':
                return Payment::STATUS_REFUNDED;
                break;

            default:
                throw new Exception("Unknown payment status {$status}");
                break;
        }
    }

    public function getPaymentStatus(Payment $payment)
    {
        $multiSafepaySdk = $this->getClient();
        $transactionManager = $multiSafepaySdk->getTransactionManager();
        return $transactionManager->get($payment->provider_id);
    }

    public function handleResponse(Payment $payment): PaymentStatusResponse
    {
        $payment_status = $this->getPaymentStatus($payment);
        $status = $this->convertStatus($payment_status->getStatus());
        $paid_amount = intval($payment_status->getAmount());
        return new PaymentStatusResponse($status, $paid_amount);
    }

    public function getCanceledAt(Payment $payment): ?Carbon
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        if ($payment->isCanceled()) {
            return Carbon::parse($info->getModified());
        }
        return null;
    }

    public function getExpiresAt(Payment $payment): ?Carbon
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        if ($payment->isExpired()) {
            return Carbon::parse($info->getModified());
        }
        return null;
    }

    public function getFailedAt(Payment $payment): ?Carbon
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        if ($payment->isFailed()) {
            return Carbon::parse($info->getModified());
        }
        return null;
    }

    public function getPaidAt(Payment $payment): ?Carbon
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        if ($payment->isPaid()) {
            return Carbon::parse($info->getModified());
        }
        return null;
    }

    public function getConsumerName(Payment $payment): ?string
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        return $info->getPaymentDetails()->getAccountHolderName();
    }

    public function getConsumerAccount(Payment $payment): ?string
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        return $info->getPaymentDetails()->getAccountIban();
    }

    public function getConsumerBic(Payment $payment): ?string
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        return $info->getPaymentDetails()->getAccountBic();
    }

    public function getPaymentTypeName(Payment $payment): ?string
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);
        return $info->getPaymentDetails()->getType();
    }
}
