<?php

return [

    'test_payments' => env('PAYABLE_TEST_PAYMENTS', false),

    'use_order_payments' => false,

    'shared_with_expose' => env('SHARED_WITH_EXPOSE', false),

    'routes' => [
        /**
         * This is the route name where all successfull pages should be redirected to.
         */
        'payment_open' => 'payment.open',
        'payment_paid' => 'payment.paid',
        'payment_failed' => 'payment.failed',
        'payment_canceled' => 'payment.canceled',
        'payment_expired' => 'payment.expired',
        'payment_unknown' => 'payment.unknown',
    ],

    'locale' => env('CASHIER_CURRENCY_LOCALE', 'nl_NL'),
    'locale_iso_639' => env('CASHIER_CURRENCY_LOCALE_ISO_639', 'nl'),


    /**
     * Advanced Settings
     */
    'models' => [
        'user' => \App\Models\User::class,
        'payment' => \Marshmallow\Payable\Models\Payment::class,
        'payment_provider' => \Marshmallow\Payable\Models\PaymentProvider::class,
        'payment_type' => \Marshmallow\Payable\Models\PaymentType::class,
        'payment_webhook' => \Marshmallow\Payable\Models\PaymentWebhook::class,
        'payment_status' => \Marshmallow\Payable\Models\PaymentStatus::class,
    ],
    'connections' => [
        'payment_type' => null,
    ],
    'nova' => [
        'resources' => [
            'payment' => \Marshmallow\Payable\Nova\Payment::class,
            'payment_provider' => \Marshmallow\Payable\Nova\PaymentProvider::class,
            'payment_type' => \Marshmallow\Payable\Nova\PaymentType::class,
        ]
    ],

    'actions' => [
        'prepare_callback' => \Marshmallow\Payable\Actions\PrepareForCallback::class,
    ],

    'rules' => [
        'icon_rules' => [200, 200]
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'multisafepay' => [
        'key' => env('MULTI_SAFE_PAY_KEY')
    ],

    'ippies' => [
        'shop_id' => env('IPPIES_SHOP_ID'),
        'key' => env('IPPIES_KEY'),
        'api' => 'https://payment.ippies.nl/paymod.php',
        'test_api' => 'https://payment.ippiestest.nl/paymod.php',
        'status_api' => 'http://api.ippies.nl',
        'status_api_key' => env('IPPIES_STATUS_KEY'),
    ],

    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID', ''),
        'secret' => env('PAYPAL_SECRET', ''),
        'mode' => env('PAYPAL_MODE', ''),
        'connection_time_out' => env('PAYPAL_COONNECTION_TIME_OUT', 30),
        'log_enabled' => env('PAYPAL_LOG_ENABLED', true),
        'file_name' => env('PAYPAL_LOG_FILE_NAME', storage_path() . '/logs/paypal.log'),
        'log_level' => env('PAYPAL_LOG_LEVEL', 'ERROR'),
    ],

    'buckaroo' => [
        'website_key' => env('BUCKAROO_WEBSITE_KEY'),
        'secret' => env('BUCKAROO_SECRET'),
    ],
];
