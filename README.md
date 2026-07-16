![alt text](https://marshmallow.dev/cdn/media/logo-red-237x46.png "marshmallow.")

# Marshmallow Payable

[![Latest Version on Packagist](https://img.shields.io/packagist/v/marshmallow/payable.svg?style=flat-square)](https://packagist.org/packages/marshmallow/payable)
[![Total Downloads](https://img.shields.io/packagist/dt/marshmallow/payable.svg?style=flat-square)](https://packagist.org/packages/marshmallow/payable)
[![PHP Syntax Checker](https://github.com/marshmallow-packages/payable/actions/workflows/php-syntax-checker.yml/badge.svg?branch=main)](https://github.com/marshmallow-packages/payable/actions/workflows/php-syntax-checker.yml)

This package will make it possible to accept payments on all our laravel resources. This was orignaly build for our e-commerce package but can be used on anything.

It ships integrations for **Mollie**, **MultiSafepay**, **Stripe** and **Buckaroo**, a set of payment models, payment status events, and a callback/webhook handler.

The package is admin-panel agnostic: it ships no Nova or Filament resources. Build the resources you need in the consuming project, against the models in `Marshmallow\Payable\Models` (or your own subclasses of them, registered via `payable.models.*`).

## Requirements

-   PHP `^8.2`
-   Laravel (provider auto-discovered via package discovery)

## Installation

### Composer

You can install the package via composer:

```bash
composer require marshmallow/payable
```

The service provider is auto-discovered. Migrations are loaded automatically from the package.

### Publish the config

```bash
php artisan vendor:publish --provider="Marshmallow\Payable\PayableServiceProvider"
```

This publishes `config/payable.php`.

### Admin resources

The package does not ship admin resources. Create them in your own project for
whichever panel it uses — Nova, Filament, or none at all — pointing at
`Marshmallow\Payable\Models\Payment`, `PaymentProvider` and `PaymentType`, or at
your own subclasses registered under `payable.models.*`.

## Configuration

After publishing you can tweak `config/payable.php`. The most relevant keys:

| Key | Default | Description |
| --- | --- | --- |
| `test_payments` | `env('PAYABLE_TEST_PAYMENTS', false)` | Run payments in test mode. |
| `use_order_payments` | `false` | Send full order/item information to the payment provider (requires the `PayableWithItems` trait). |
| `shared_with_expose` | `env('SHARED_WITH_EXPOSE', false)` | Set when sharing the app through Expose so callback/webhook URLs resolve correctly. |
| `mollie.capture_mode` | `env('PAYABLE_MOLLIE_CAPTURE_MODE')` | Set to `manual` for the authorize → capture flow used by pay-later methods (klarna, billie, in3, riverty). Leave `null` for immediate capture. |
| `routes.*` | `payment.open`, `payment.paid`, ... | Named routes a payment status redirects to (`payment_open`, `payment_paid`, `payment_failed`, `payment_canceled`, `payment_expired`, `payment_unknown`). |
| `locale` | `env('CASHIER_CURRENCY_LOCALE', 'nl_NL')` | Currency locale. |
| `locale_iso_639` | `env('CASHIER_CURRENCY_LOCALE_ISO_639', 'nl')` | ISO 639 language code. |
| `models.*` | Package models | Override the `payment`, `payment_provider`, `payment_type`, `payment_webhook`, `payment_status` and `user` models. |
| `actions.prepare_callback` | `PrepareForCallback::class` | Action invoked when preparing a payment callback. |
| `stripe.*` | env-driven | Stripe `key`, `secret`, `webhook` secret and the subscribed Stripe event types. |
| `multisafepay.key` | `env('MULTI_SAFE_PAY_KEY')` | MultiSafepay API key. |
| `buckaroo.*` | env-driven | Buckaroo `website_key` and `secret`. |

### Environment variables

```env
MOLLIE_KEY="test_*****"
MULTI_SAFE_PAY_KEY="*****"
PAYABLE_TEST_PAYMENTS=true
```

## Usage

### Prepare your models

Add the `Payable` trait to the model that should support payments and implement the required abstract methods:

```php
use Marshmallow\Payable\Traits\Payable;

class Order extends Model
{
    use Payable;

    public function getTotalAmount(): int
    {
        return $this->total_in_cents;
    }

    public function getPayableDescription(): string
    {
        return "Order #{$this->id}";
    }

    public function getCustomerName(): ?string
    {
        return $this->customer?->name;
    }

    public function getCustomerEmail(): ?string
    {
        return $this->customer?->email;
    }

    public function getCustomer(): ?\Illuminate\Database\Eloquent\Model
    {
        return $this->customer;
    }
}
```

### Start a payment

```php
use Marshmallow\Payable\Models\PaymentType;

$paymentType = PaymentType::first();

$order->startPayment($paymentType);

// Recurring payment
$order->startRecurringPayment($paymentType);
```

The payments related to a model are available through the `payments()` morph relation.

### Use order information

First let the payable package know we want to sent order information to the payment provider.

```php
return [
    'use_order_payments' => true,
]
```

Add the trait `PayableWithItems` to your Payable model.

Implements the following methods on your Payable model.

```php
getBillingOrganizationName(),
getBillingTitle(),
getBillingGivenName(), //required
getBillingFamilyName(), //required
getBillingEmailaddress(), //required
getBillingPhonenumber(),
getBillingStreetAndNumber(), //required
getBillingStreetAdditional(),
getBillingPostalCode(),
getBillingCity(), //required
getBillingRegion(),
getBillingCountry(), //required
```

## Events

The package dispatches the following events as a payment moves through its lifecycle:

```php
PaymentStatusOpen::class
PaymentStatusPaid::class
PaymentStatusFailed::class
PaymentStatusCanceled::class
PaymentStatusExpired::class
PaymentStatusRefunded::class
PaymentStatusUnknown::class
ExternalCustomerModified::class
```

## Providers

### Multisafe pay

-   [x] Simple checkout
-   [ ] Complex checkout

### Mollie

-   [ ] Simple checkout
-   [ ] Complex checkout

### Tests

Test mollie simple checkout

```php
\Marshmallow\Payable\Facades\PayableTest::mollie($test = false, $api_key = 'live_xxxx');
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Upgrading

Please see [UPGRADE](UPGRADE.md) for details on upgrading between versions.

## Security

If you discover any security related issues, please email stef@marshmallow.dev instead of using the issue tracker.

## Credits

-   [Stef](https://marshmallow.dev)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
