# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

-   **Mollie:** a 0% VAT line is now sent with `vatRate: "0.00"` instead of
    `"0"`. The SDK's payload factory reads fields with a truthiness check
    (`Factory::get()`), so the falsy `"0"` was silently dropped while the
    line's `vatAmount` of `"0.00"` survived, and Mollie rejected every such
    payment with a 422: "The 'vatRate' field is missing, but the 'vatAmount'
    field is present". Any checkout with a 0% line (intra-EU reverse charge,
    exempt goods) failed. Rates now always use Mollie's documented two-decimal
    shape (`formatVatRate()`).
-   **Mollie:** the timestamp getters (`getExpiresAt()`, `getCanceledAt()`,
    `getFailedAt()`, `getPaidAt()`) now read the field null-safely and return
    null when Mollie did not send it. Mollie drops `expiresAt` once a payment
    has expired (`expiredAt` replaces it, and is now preferred), and for a
    legacy `ord_` order the status is a raw stdClass, so every webhook for an
    expired legacy order threw `Undefined property: stdClass::$expiresAt`
    and was retried by Mollie indefinitely. (Backport of the v4 fix.)

## [3.0.0] - TBD

### BREAKING CHANGES
-   **BREAKING:** Bumped `mollie/laravel-mollie` from `^3.0` to `^4.0` (pulls in
    `mollie/mollie-api-php` v3) and raised the minimum PHP version from `^8.1` to
    `^8.2`. This unblocks Laravel 13 support.
-   Migrated the Mollie provider from the removed Orders API to the Payments API
    ([migration guide](https://docs.mollie.com/docs/migrating-from-orders-to-payments)):
    -   `createOrder()` now calls `payments->create()` with a `lines` array
        instead of `orders->create()`. `orderNumber` moved to
        `metadata.order_number` and each line `name` became `description`.
    -   **BREAKING:** `createShipment()` / `createShipmentWithTracking()` now
        create amount-based payment captures instead of order shipments. Their
        signatures changed from `array $lines` to `?int $amount`; tracking is
        stored in capture metadata. Update any callers.
    -   `refund()` and `getPaymentStatus()` now use the `payments` endpoint
        exclusively.
-   Added `payable.mollie.capture_mode` config (env `PAYABLE_MOLLIE_CAPTURE_MODE`)
    for the pay-later authorize → capture flow (klarna, billie, in3, riverty).
-   Bumped dev dependencies to match the new Laravel 11/12 floor:
    `orchestra/testbench` `^6.2` → `^9.0 || ^10.0`, `phpunit/phpunit`
    `^9.5` → `^10.5 || ^11.0`. Migrated `phpunit.xml` to the PHPUnit 11 schema.
- **PayPal Provider Removed**: The PayPal payment provider has been completely removed from the package
  - The `PayPal` provider class has been deleted
  - PayPal constants and references removed from the main `Payable` class
  - PayPal configuration options removed from `config/payable.php`
  - PayPal webhook routes and controller methods removed
  - PayPal SDK dependency (`paypal/paypal-checkout-sdk`) removed from composer.json
  - PayPal test methods removed from `PayableTest` class
  - PayPal option removed from Nova payment provider resource
- **Ippies Provider Removed**: The Ippies payment provider has been completely removed from the package
  - The `Ippies` provider class and entire `Services/Ippies/` directory deleted
  - Ippies constants and references removed from the main `Payable` class
  - Ippies configuration options removed from `config/payable.php`
  - Ippies test methods removed from `PayableTest` class
  - Ippies option removed from Nova payment provider resource

### Migration Guide
If you were using PayPal in your application:
1. Remove any PayPal payment providers from your database
2. Remove PayPal-related environment variables (PAYPAL_CLIENT_ID, PAYPAL_SECRET, etc.)
3. Update any code that referenced `Payable::PAYPAL` constant
4. Consider migrating to an alternative payment provider (Stripe, Mollie, etc.)

If you were using Ippies in your application:
1. Remove any Ippies payment providers from your database
2. Remove Ippies-related environment variables (IPPIES_SHOP_ID, IPPIES_KEY, IPPIES_STATUS_KEY, etc.)
3. Update any code that referenced `Payable::IPPIES` constant
4. Consider migrating to an alternative payment provider (Stripe, Mollie, etc.)

### Removed

-   Dropped the `consumerDateOfBirth` field and the `completed` payment status
    (both Orders-API only).
-   Removed dead Mollie imports from the Buckaroo provider.
-   Removed unused `marshmallow/helpers` and `marshmallow/commands` dependencies
    (no references anywhere in the package).

### Deprecated

-   Legacy Mollie order ids (`ord_…`) can no longer be fetched, refunded or
    shipped. Those operations now throw a clear exception.

See [`UPGRADE.md`](UPGRADE.md) for upgrade instructions.


## [1.0.0] - 2021-01-26
