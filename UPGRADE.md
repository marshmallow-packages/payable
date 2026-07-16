# Upgrade Guide

## Nova resources removed

The package no longer ships Nova resources. `src/Nova/Payment.php`,
`src/Nova/PaymentProvider.php` and `src/Nova/PaymentType.php` are gone, the
`nova.resources.*` config block is gone, and the `marshmallow/nova-tinymce`
dependency — and with it `laravel/nova` — is no longer required.

This is a **breaking change** for projects that registered the package's Nova
resources.

### Why

The resources extended `App\Nova\Resource`, a class in the *consuming*
application. The package therefore depended on its consumers, could not be
tested in isolation, and dragged a `laravel/nova` requirement into every project
using it — including projects that use Filament, or no admin panel at all. That
dependency also made `composer install` fail for anyone without a Nova licence,
which is why this package had no runnable test suite.

Payment admin screens are project-level concerns. They differ per project
anyway, and they are cheap to write.

### Action required

**If you do not register the package's Nova resources, no action is needed.**
Nova auto-discovers `app/Nova` only, so unless you explicitly listed
`Marshmallow\Payable\Nova\*` in your `NovaServiceProvider`, they were never
loaded and removing them changes nothing.

**If you do register them,** create the resources in your own project before
upgrading. Point them at the models you have configured under
`payable.models.*`:

```php
namespace App\Nova;

class Payment extends Resource
{
    public static $model = \Marshmallow\Payable\Models\Payment::class;

    // ... fields
}
```

The 2.x resources are a usable starting point — copy them out of your `vendor/`
directory (or from the git history) before upgrading, and adjust.

**Remove the `nova` block from your published `config/payable.php`.** It now
points at classes that no longer exist. Nothing reads it, so a stale block is
harmless, but it is dead configuration.

**If you referenced `config('payable.nova.resources.*')`** anywhere, replace it
with your own resource class names.

## Upgrading to the Mollie Payments API (mollie/laravel-mollie v4)

This release bumps `mollie/laravel-mollie` from `^3.0` to `^4.0`, which pulls in
`mollie/mollie-api-php` v3. **Mollie has removed the Orders API** in this SDK; it
is replaced by the Payments API with order line details and captures. See
Mollie's [Migrating from Orders to Payments](https://docs.mollie.com/docs/migrating-from-orders-to-payments).

This is a **breaking change**. Requirements are now **PHP >= 8.2** and
**Laravel >= 11** (transitively, via laravel-mollie v4). It also unblocks
**Laravel 13** support.

### What changed in the Mollie provider

| Before (Orders API) | After (Payments API v3) |
|---|---|
| `createOrder()` → `orders->create()` | `createOrder()` → `payments->create()` with a `lines` array |
| order `orderNumber` | moved to payment `metadata.order_number` |
| line `name` | line `description` |
| `consumerDateOfBirth` | removed (not supported on payments) |
| `createShipment(Payment, array $lines)` | `createShipment(Payment, ?int $amount)` → a payment **capture** |
| `createShipmentWithTracking(Payment, array $lines, array $tracking)` | `createShipmentWithTracking(Payment, ?int $amount, array $tracking)` |
| order `refundAll()` | `payments->refund()` with an amount |
| `orders->get()` for status | `payments->get()` |

### Action required

- **Shipments are now captures and are amount-based, not line-based.** If you
  previously shipped specific order lines, pass the summed amount (in cents) to
  capture, or `null` to capture the full authorized amount. The
  `createShipment*()` method signatures changed accordingly — update any callers.

- **Pay-later flow (klarna / billie / in3 / riverty).** To keep the
  authorize → ship → capture flow, set `payable.mollie.capture_mode` to
  `manual` (env `PAYABLE_MOLLIE_CAPTURE_MODE=manual`). Without it, payments are
  captured immediately and reach `paid` directly (there is nothing to capture).
  Note that manual capture is only valid for methods that support it.

- **Legacy Mollie order ids (`ord_…`) can no longer be processed.** The Orders
  API is gone, so fetching/refunding/shipping an old order now throws a clear
  exception. Only payments (`tr_…`) created after this upgrade are supported.

- **Statuses.** Payments do not have a `completed` status (that was
  Orders-only); terminal success is `paid`. The provider's `convertStatus()`
  still accepts the old values defensively.

### Not affected

- `use_order_payments = false` (the default) already used the Payments API and
  keeps working unchanged.
- The Buckaroo, MultiSafepay, Stripe, PayPal and Ippies providers are
  unaffected (Buckaroo only carried unused Mollie imports, now removed).
