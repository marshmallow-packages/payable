<?php

return [

    'test_payments' => env('PAYABLE_TEST_PAYMENTS', false),

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
        'payment' => \Marshmallow\Payable\Models\Payment::class,
        'payment_provider' => \Marshmallow\Payable\Models\PaymentProvider::class,
        'payment_type' => \Marshmallow\Payable\Models\PaymentType::class,
        'payment_webhook' => \Marshmallow\Payable\Models\PaymentWebhook::class,
        'payment_status' => \Marshmallow\Payable\Models\PaymentStatus::class,
    ],
    'nova' => [
        'resources' => [
            'payment' => \Marshmallow\Payable\Nova\Payment::class,
            'payment_provider' => \Marshmallow\Payable\Nova\PaymentProvider::class,
            'payment_type' => \Marshmallow\Payable\Nova\PaymentType::class,
        ]
    ],
    'rules' => [
        'icon_rules' => [200, 200]
    ],
];
