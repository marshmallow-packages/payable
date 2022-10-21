<?php

namespace Marshmallow\Payable\Providers;

use Exception;
use Carbon\Carbon;
use Illuminate\Http\Request;
use MultiSafepay\ValueObject\Money;
use Marshmallow\Payable\Models\Payment;
use MultiSafepay\ValueObject\IpAddress;
use MultiSafepay\ValueObject\Customer\Address;
use MultiSafepay\ValueObject\Customer\Country;
use MultiSafepay\Api\Transactions\OrderRequest;
use MultiSafepay\ValueObject\Customer\PhoneNumber;
use MultiSafepay\ValueObject\Customer\EmailAddress;
use Marshmallow\Payable\Http\Responses\PaymentStatusResponse;
use Marshmallow\Payable\Providers\Contracts\PaymentProviderContract;
use MultiSafepay\Api\Transactions\OrderRequest\Arguments\ShoppingCart;
use MultiSafepay\Api\Transactions\OrderRequest\Arguments\PluginDetails;
use MultiSafepay\Api\Transactions\OrderRequest\Arguments\PaymentOptions;
use MultiSafepay\Api\Transactions\OrderRequest\Arguments\CustomerDetails;
use MultiSafepay\Api\Transactions\OrderRequest\Arguments\ShoppingCart\Item;

class MultiSafePay extends Provider implements PaymentProviderContract
{
    protected function getClient()
    {
        $test_payment = config('payable.test_payments');
        $is_production = (!$test_payment);
        return new \MultiSafepay\Sdk(config('payable.multisafepay.key'), $is_production);
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

        if (config('payable.use_order_payments') === true) {

            $items = [];
            $this->payableModel->items->each(function ($item) use (&$items) {
                $items[] = (new Item())
                    ->addName($item->description)
                    ->addUnitPrice(new Money($item->price_excluding_vat, 'EUR')) // Amount must be in cents
                    ->addQuantity($item->quantity)
                    ->addDescription($item->description)
                    ->addTaxRate($item->vatrate->rate)
                    ->addTaxTableSelector(
                        $item->vatrate->name
                    )
                    ->addMerchantItemId(
                        $item->product_id ?? $item->type
                    );
            });

            $payabel_model = $this->payableModel;

            $address = (new Address())
                ->addStreetName($payabel_model->getShippingStreetName())
                ->addHouseNumber($payabel_model->getShippingHouseNumber())
                ->addZipCode($payabel_model->getShippingZipCode())
                ->addCity($payabel_model->getShippingCity())
                ->addCountry(new Country($payabel_model->getShippingCountry()));

            $customer = (new CustomerDetails())
                ->addFirstName($payabel_model->getCustomerFirstName())
                ->addLastName($payabel_model->getCustomerLastName())
                ->addAddress($address)
                ->addEmailAddress(new EmailAddress($payabel_model->getCustomerEmail()))
                ->addPhoneNumber(new PhoneNumber($payabel_model->getCustomerPhoneNumber()))
                ->addLocale($payabel_model->getCustomerLocale())
                ->addUserAgent(request()->header('User-Agent'))
                ->addForwardedIp(new IpAddress(request()->ip()));

            $orderRequest = $orderRequest->addCustomer($customer)
                ->addDelivery($customer)
                ->addShoppingCart(new ShoppingCart($items));
        }

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
