![alt text](https://marshmallow.dev/cdn/media/logo-red-237x46.png "marshmallow.")

# Marshmallow Payable

This package will make it possible to accept payments on all our laravel resources. This was orignaly build for our e-commerce package but can be used on anything.

## Installation

### Composer

You can install the package via composer:

```
composer require marshmallow/payable
```

### Publish Nova Resources

```bash
php artisan marshmallow:resource Payment Payable
php artisan marshmallow:resource PaymentProvider Payable
php artisan marshmallow:resource PaymentType Payable
```

## Usage

```env
MOLLIE_KEY=""
PAYABLE_TEST_PAYMENTS=true
```

### Prepare your models

Add the `Payable` trait to your model that should support payments.

## Providers

### Multisafe pay

-   [x] Simple checkout
-   [ ] Complex checkout

### Mollie

-   [ ] Simple checkout
-   [ ] Complex checkout

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Security

If you discover any security related issues, please email stef@marshmallow.dev instead of using the issue tracker.

## Credits

-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
