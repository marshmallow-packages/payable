<?php

namespace Marshmallow\Payable\Providers;

use Exception;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use OnlinePayments\Sdk\Client;
use OnlinePayments\Sdk\Communicator;
use OnlinePayments\Sdk\ReferenceException;
use Marshmallow\Payable\Models\Payment;
use OnlinePayments\Sdk\Domain\Order;
use OnlinePayments\Sdk\Domain\AmountOfMoney;
use OnlinePayments\Sdk\Domain\OrderReferences;
use OnlinePayments\Sdk\Domain\RefundRequest;
use OnlinePayments\Sdk\Webhooks\WebhooksHelper;
use Marshmallow\Payable\Models\PaymentProvider;
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
    protected $hostedCheckout;

    protected bool $hostedCheckoutResolved = false;

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
        return $this->merchantClient()->hostedCheckout()->createHostedCheckout(
            $this->buildHostedCheckoutRequest()
        );
    }

    /**
     * Building the request is separate from sending it so tests can assert
     * what is actually sent to Worldline — most importantly that the merchant
     * reference carries the payable identifier, which back offices reconcile
     * prepayments on.
     */
    public function buildHostedCheckoutRequest(): CreateHostedCheckoutRequest
    {
        $amountOfMoney = new AmountOfMoney;
        $amountOfMoney->setAmount($this->getPayableAmount());
        $amountOfMoney->setCurrencyCode($this->getCurrencyIso4217Code());

        $references = new OrderReferences;
        $references->setMerchantReference($this->merchantReference());

        $order = new Order;
        $order->setAmountOfMoney($amountOfMoney);
        $order->setReferences($references);

        $hostedCheckoutInput = new HostedCheckoutSpecificInput;
        $hostedCheckoutInput->setReturnUrl($this->redirectUrl());
        $hostedCheckoutInput->setLocale(config('payable.locale'));

        $request = new CreateHostedCheckoutRequest;
        $request->setOrder($order);
        $request->setHostedCheckoutSpecificInput($hostedCheckoutInput);

        return $request;
    }

    /**
     * The payable decides the reference (via getPayableIdentifier) so it can
     * expose the number its back office reconciles on. Deliberately not routed
     * through Provider::getPayableIdentifier(): that falls back to the payable
     * description ("Order #1234"), which is neither unique nor charset-safe.
     */
    protected function merchantReference(): string
    {
        if (method_exists($this->payableModel, 'getPayableIdentifier')) {
            $identifier = $this->payableModel->getPayableIdentifier();

            if (filled($identifier)) {
                return (string) $identifier;
            }
        }

        return (string) $this->payment->id;
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
     * is known: Worldline signs the raw body and posts to a single endpoint.
     *
     * Resolution is tiered because the merchant reference now carries the
     * payable's order number instead of our payment id:
     * 1. The event's payment id is "{hostedCheckoutId}_{n}" and our
     *    provider_id stores the hosted checkout id — the primary lookup,
     *    which also covers payments created before this change.
     * 2. Legacy references that still hold a payment id.
     * 3. The payable whose identifier matches the reference, when that leaves
     *    exactly one open Worldline payment (guards against Worldline's
     *    documented payment-id format instability).
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

        $hostedCheckoutId = Str::before((string) $worldlinePayment->getId(), '_');
        if (filled($hostedCheckoutId)) {
            $payment = $this->worldlinePaymentQuery()
                ->where('provider_id', $hostedCheckoutId)
                ->first();

            if ($payment) {
                return $payment;
            }
        }

        $merchantReference = $worldlinePayment
            ->getPaymentOutput()
            ?->getReferences()
            ?->getMerchantReference();

        if (!$merchantReference) {
            return null;
        }

        if ($payment = config('payable.models.payment')::find($merchantReference)) {
            return $payment;
        }

        return $this->resolveByPayableIdentifier($merchantReference);
    }

    /**
     * Match the merchant reference against the payable identifiers of open
     * Worldline payments. Only trusted when it resolves to exactly one
     * payment: the reference is a reconciliation aid, not a unique key.
     */
    protected function resolveByPayableIdentifier(string $merchantReference): ?Payment
    {
        $candidates = $this->worldlinePaymentQuery()
            ->where('status', Payment::STATUS_OPEN)
            ->latest()
            ->take(50)
            ->get()
            ->filter(function ($payment) use ($merchantReference) {
                $payable = $payment->payable;

                return $payable
                    && method_exists($payable, 'getPayableIdentifier')
                    && (string) $payable->getPayableIdentifier() === $merchantReference;
            });

        return 1 === $candidates->count() ? $candidates->first() : null;
    }

    /**
     * Constrained to Worldline payments so a provider_id collision with
     * another provider's payment can never misroute a webhook.
     */
    protected function worldlinePaymentQuery()
    {
        return config('payable.models.payment')::query()
            ->whereHas('provider', function ($query): void {
                $query->where('type', PaymentProvider::PROVIDER_WORLDLINE);
            });
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

    /**
     * Worldline keeps a hosted checkout for three hours (the default
     * hostedCheckoutSpecificInput.sessionTimeout). After that the status
     * endpoint answers 404, which the SDK raises as a ReferenceException.
     * A consumer, or a link-preview crawler, revisiting the return URL later
     * is normal traffic rather than an error, so a gone checkout resolves to
     * null and the callers fall back to what the payment already stores: by
     * then the webhook has delivered the final status.
     *
     * Resolved once per provider instance. The status update reads the
     * checkout for the status and again for the timestamps and consumer
     * details, and the base class memo would build a fresh provider for
     * the second read.
     */
    protected function hostedCheckoutFor(Payment $payment)
    {
        if ($this->hostedCheckoutResolved) {
            return $this->hostedCheckout;
        }

        $this->hostedCheckoutResolved = true;

        try {
            $this->hostedCheckout = $this->getPaymentStatus($payment);
        } catch (ReferenceException) {
            $this->hostedCheckout = null;
        }

        return $this->hostedCheckout;
    }

    protected function getPaymentInfoFromTheProvider(Payment $payment)
    {
        return $this->hostedCheckoutFor($payment);
    }

    public function handleResponse(Payment $payment): PaymentStatusResponse
    {
        $hostedCheckout = $this->hostedCheckoutFor($payment);

        if (!$hostedCheckout) {
            return new PaymentStatusResponse(
                $payment->status ?: Payment::STATUS_OPEN,
                (int) $payment->paid_amount,
            );
        }

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

        return $info?->getCreatedPaymentOutput()?->getPayment();
    }

    /**
     * The timestamp and consumer getters fall back to the stored column so
     * that a status update without the hosted checkout keeps what an earlier
     * webhook or return already recorded instead of blanking it.
     */
    public function getPaidAt(Payment $payment): ?Carbon
    {
        return $this->statusChangedAt($payment) ?? $payment->paid_at;
    }

    public function getCanceledAt(Payment $payment): ?Carbon
    {
        return $this->statusChangedAt($payment) ?? $payment->canceled_at;
    }

    public function getFailedAt(Payment $payment): ?Carbon
    {
        return $this->statusChangedAt($payment) ?? $payment->failed_at;
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
        return $this->customerBankAccount($payment)?->getAccountHolderName() ?? $payment->consumer_name;
    }

    public function getConsumerAccount(Payment $payment): ?string
    {
        return $this->customerBankAccount($payment)?->getIban() ?? $payment->consumer_account;
    }

    public function getConsumerBic(Payment $payment): ?string
    {
        return $this->customerBankAccount($payment)?->getBic() ?? $payment->consumer_bic;
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
            null => $payment->payment_type_name,
            default => (string) $productId,
        };
    }

    public function isSignatureValidationException(Exception $exception): bool
    {
        return $exception instanceof SignatureValidationException;
    }
}
