<?php

namespace Marshmallow\Payable\Providers;

use Exception;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use OnlinePayments\Sdk\Client;
use OnlinePayments\Sdk\Communicator;
use Marshmallow\Payable\Models\Payment;
use OnlinePayments\Sdk\Domain\Order;
use OnlinePayments\Sdk\Domain\AmountOfMoney;
use OnlinePayments\Sdk\Domain\OrderReferences;
use OnlinePayments\Sdk\Domain\RefundRequest;
use OnlinePayments\Sdk\Webhooks\WebhooksHelper;
use Marshmallow\Payable\Resources\PaymentRefund;
use OnlinePayments\Sdk\CommunicatorConfiguration;
use OnlinePayments\Sdk\Authentication\V1HmacAuthenticator;
use OnlinePayments\Sdk\Webhooks\InMemorySecretKeyStore;
use Marshmallow\Payable\Http\Responses\PaymentStatusResponse;
use OnlinePayments\Sdk\Domain\CreateHostedCheckoutRequest;
use OnlinePayments\Sdk\Domain\HostedCheckoutSpecificInput;
use OnlinePayments\Sdk\Webhooks\SignatureValidationException;
use Marshmallow\Payable\Providers\Contracts\PaymentProviderContract;

/**
 * Worldline Direct (formerly Ingenico ePayments) hosted checkout provider.
 *
 * Unlike Mollie, Worldline delivers webhooks to a single endpoint configured
 * in the back office rather than a per-payment URL. The webhook is therefore
 * routed through PaymentCallbackController::worldline(), which resolves the
 * payment from the event body instead of the route, the same way Stripe is
 * handled. The return flow still uses the per-payment redirect URL.
 */
class Worldline extends Provider implements PaymentProviderContract
{
    protected function getClient(): Client
    {
        $configuration = new CommunicatorConfiguration(
            config('payable.worldline.api_key_id'),
            config('payable.worldline.api_secret'),
            config('payable.worldline.api_endpoint'),
            config('payable.worldline.integrator', 'Marshmallow-Payable'),
        );

        $communicator = new Communicator(
            $configuration,
            new V1HmacAuthenticator($configuration),
        );

        return new Client($communicator);
    }

    protected function merchantClient()
    {
        return $this->getClient()->merchant(
            config('payable.worldline.merchant_id'),
        );
    }

    public function createPayment($api_key = null)
    {
        $amountOfMoney = new AmountOfMoney;
        $amountOfMoney->setAmount($this->getPayableAmount());
        $amountOfMoney->setCurrencyCode($this->getCurrencyIso4217Code());

        /**
         * The merchant reference is our own payment id. The single-endpoint
         * webhook has no route parameter to resolve the payment from, so it
         * looks the payment up by this reference.
         */
        $references = new OrderReferences;
        $references->setMerchantReference($this->payment->id);

        $order = new Order;
        $order->setAmountOfMoney($amountOfMoney);
        $order->setReferences($references);

        $hostedCheckoutInput = new HostedCheckoutSpecificInput;
        $hostedCheckoutInput->setReturnUrl($this->redirectUrl());
        $hostedCheckoutInput->setLocale(config('payable.locale'));

        $request = new CreateHostedCheckoutRequest;
        $request->setOrder($order);
        $request->setHostedCheckoutSpecificInput($hostedCheckoutInput);

        return $this->merchantClient()->hostedCheckout()->createHostedCheckout($request);
    }

    public function getPaymentId()
    {
        return $this->provider_payment_object->getHostedCheckoutId();
    }

    public function getPaymentUrl(): string
    {
        return $this->provider_payment_object->getRedirectUrl();
    }

    public function handleReturnNotification(Payment $payment, Request $request): PaymentStatusResponse
    {
        return $this->handleResponse($payment);
    }

    public function handleWebhookNotification(Payment $payment, Request $request): PaymentStatusResponse
    {
        return $this->handleResponse($payment);
    }

    /**
     * Verify the webhook signature and return the payment the event refers to.
     *
     * Called by the dedicated Worldline webhook controller, before the payment
     * is known: Worldline signs the raw body and posts to a single endpoint, so
     * the payment is resolved from the event's merchant reference.
     */
    public function resolvePaymentFromWebhook(Request $request): ?Payment
    {
        $secretKeyStore = new InMemorySecretKeyStore([
            config('payable.worldline.webhook_key_id') => config('payable.worldline.webhook_secret'),
        ]);

        $event = (new WebhooksHelper($secretKeyStore))->unmarshal(
            $request->getContent(),
            $this->flattenHeaders($request),
        );

        $worldlinePayment = $event->getPayment();
        if (!$worldlinePayment) {
            return null;
        }

        $merchantReference = $worldlinePayment
            ->getPaymentOutput()
            ?->getReferences()
            ?->getMerchantReference();

        if (!$merchantReference) {
            return null;
        }

        return config('payable.models.payment')::find($merchantReference);
    }

    /**
     * The SDK's signature validator reads header values as plain strings, but
     * Symfony exposes each header as a list of values. Collapse each to its
     * first value so the X-GCS-Signature and X-GCS-KeyId headers arrive as the
     * strings the validator expects.
     */
    protected function flattenHeaders(Request $request): array
    {
        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $headers[$name] = is_array($values) ? Arr::first($values) : $values;
        }

        return $headers;
    }

    public function convertStatus($status): string
    {
        switch ($status) {
            case 'CREATED':
            case 'REDIRECTED':
            case 'PENDING_PAYMENT':
            case 'PENDING_FRAUD_APPROVAL':
            case 'PENDING_APPROVAL':
            case 'PENDING_COMPLETION':
            case 'PENDING_CAPTURE':
            case 'AUTHORIZATION_REQUESTED':
            case 'CAPTURE_REQUESTED':
                return Payment::STATUS_OPEN;

            case 'PAID':
            case 'CAPTURED':
            case 'ACCOUNT_VERIFIED':
                return Payment::STATUS_PAID;

            case 'CANCELLED':
                return Payment::STATUS_CANCELED;

            case 'REJECTED':
            case 'REJECTED_CAPTURE':
                return Payment::STATUS_FAILED;

            case 'REVERSED':
                return Payment::STATUS_CANCELED;

            case 'REFUNDED':
                return Payment::STATUS_REFUNDED;

            default:
                throw new Exception("Unknown payment status {$status}");
        }
    }

    public function getPaymentStatus(Payment $payment)
    {
        return $this->merchantClient()->hostedCheckout()->getHostedCheckout($payment->provider_id);
    }

    public function handleResponse(Payment $payment): PaymentStatusResponse
    {
        $hostedCheckout = $this->getPaymentStatus($payment);
        $createdPayment = $hostedCheckout->getCreatedPaymentOutput()?->getPayment();

        if (!$createdPayment) {
            /**
             * The consumer has not completed the hosted checkout yet, so there
             * is no payment to read a status from.
             */
            return new PaymentStatusResponse(Payment::STATUS_OPEN, 0);
        }

        $status = $this->convertStatus($createdPayment->getStatus());
        $paid_amount = $createdPayment->getPaymentOutput()?->getAmountOfMoney()?->getAmount() ?? 0;

        return new PaymentStatusResponse($status, $paid_amount);
    }

    public function refund(Payment $payment, int $amount, $api_key = null)
    {
        $amountOfMoney = new AmountOfMoney;
        $amountOfMoney->setAmount($amount);
        $amountOfMoney->setCurrencyCode($this->getCurrencyIso4217Code());

        $request = new RefundRequest;
        $request->setAmountOfMoney($amountOfMoney);

        $worldlinePaymentId = $this->getWorldlinePaymentId($payment);
        $result = $this->merchantClient()->payments()->refundPayment($worldlinePaymentId, $request);

        return new PaymentRefund(
            provider_id: $result->getId(),
            status: $result->getStatus(),
        );
    }

    /**
     * Our provider_id is the hosted checkout id, but payment operations such as
     * refunds need the underlying payment id. Resolve it through the hosted
     * checkout.
     */
    protected function getWorldlinePaymentId(Payment $payment): string
    {
        $hostedCheckout = $this->getPaymentStatus($payment);
        $worldlinePayment = $hostedCheckout->getCreatedPaymentOutput()?->getPayment();

        if (!$worldlinePayment) {
            throw new Exception("No Worldline payment found for {$payment->provider_id}");
        }

        return $worldlinePayment->getId();
    }

    protected function createdPaymentFor(Payment $payment)
    {
        $info = $this->getPaymentInfoFromTheProvider($payment);

        return $info->getCreatedPaymentOutput()?->getPayment();
    }

    public function getPaidAt(Payment $payment): ?Carbon
    {
        return $this->statusChangedAt($payment);
    }

    public function getCanceledAt(Payment $payment): ?Carbon
    {
        return $this->statusChangedAt($payment);
    }

    public function getFailedAt(Payment $payment): ?Carbon
    {
        return $this->statusChangedAt($payment);
    }

    /**
     * The contract declares this public while the base provider declares it
     * protected, so every concrete provider has to redeclare it. Worldline
     * hosted checkout has no expiry timestamp to read, so it stays null.
     */
    public function getExpiresAt(Payment $payment): ?Carbon
    {
        return null;
    }

    protected function statusChangedAt(Payment $payment): ?Carbon
    {
        $createdPayment = $this->createdPaymentFor($payment);
        $changedAt = $createdPayment?->getStatusOutput()?->getStatusCodeChangeDateTime();

        return $changedAt ? Carbon::parse($changedAt) : null;
    }

    protected function customerBankAccount(Payment $payment)
    {
        $createdPayment = $this->createdPaymentFor($payment);

        return $createdPayment
            ?->getPaymentOutput()
            ?->getRedirectPaymentMethodSpecificOutput()
            ?->getCustomerBankAccount();
    }

    public function getConsumerName(Payment $payment): ?string
    {
        return $this->customerBankAccount($payment)?->getAccountHolderName();
    }

    public function getConsumerAccount(Payment $payment): ?string
    {
        return $this->customerBankAccount($payment)?->getIban();
    }

    public function getConsumerBic(Payment $payment): ?string
    {
        return $this->customerBankAccount($payment)?->getBic();
    }

    public function getPaymentTypeName(Payment $payment): ?string
    {
        $createdPayment = $this->createdPaymentFor($payment);
        $productId = $createdPayment
            ?->getPaymentOutput()
            ?->getRedirectPaymentMethodSpecificOutput()
            ?->getPaymentProductId();

        return match ($productId) {
            809 => 'ideal',
            null => null,
            default => (string) $productId,
        };
    }

    public function isSignatureValidationException(Exception $exception): bool
    {
        return $exception instanceof SignatureValidationException;
    }
}
