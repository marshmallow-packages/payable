# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.0] - TBD

### BREAKING CHANGES
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

## [1.0.0] - 2021-01-26
