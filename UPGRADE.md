# Upgrade Guide

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
