# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.0] - TBD

### BREAKING CHANGES
-   **BREAKING:** Removed the Adyen remnants. `Payable::ADYEN`,
    `Payable::getProvider()`'s Adyen branch and
    `PaymentCallbackController::adyen()` all referred to
    `Marshmallow\Payable\Providers\Adyen`, which has never existed in this
    repository — so the package advertised a provider that fataled with
    "Class not found" on use. The controller method was unroutable and had a
    leftover `dd()` in it besides. Adyen was never supported; the constant now
    reflects that. A payment type with an unknown provider type gets the regular
    "This provider is not implemented yet" exception. If Adyen support is wanted,
    it needs a real provider class. ([#99](https://github.com/marshmallow-packages/payable/issues/99))
-   **BREAKING:** Removed the bundled Nova resources (`src/Nova/Payment.php`,
    `src/Nova/PaymentProvider.php`, `src/Nova/PaymentType.php`), the
    `nova.resources.*` config block, and the `marshmallow/nova-tinymce`
    dependency that pulled in `laravel/nova`. The resources extended
    `App\Nova\Resource` from the consuming application, so the package depended
    on its own consumers and forced a Nova licence on every project — including
    Filament projects. Build payment admin resources in the consuming project
    instead. See [UPGRADE.md](UPGRADE.md#nova-resources-removed).

    Dropping `laravel/nova` also makes `composer install` work without a Nova
    licence, so the package's test suite can run again.
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
-   Added a **Worldline Direct** provider (`Marshmallow\Payable\Providers\Worldline`)
    built on `wl-online-payments-direct/sdk-php`. Hosted-checkout redirect flow,
    status mapping, refunds, and iDEAL consumer details (IBAN, BIC, account
    holder). Registered as `Payable::WORLDLINE` /
    `PaymentProvider::PROVIDER_WORLDLINE`, configured under `payable.worldline.*`.
    Because Worldline posts every webhook to one back-office-configured endpoint,
    it has a dedicated `payable.worldline.webhook` route that verifies the
    HMAC-SHA256 signature and resolves the payment from the event's merchant
    reference, rather than the per-payment webhook route.
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
