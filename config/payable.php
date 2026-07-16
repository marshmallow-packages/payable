<?php

return [

    'test_payments' => env('PAYABLE_TEST_PAYMENTS', false),

    'use_order_payments' => false,

    'shared_with_expose' => env('SHARED_WITH_EXPOSE', false),

    /**
     * Mollie specific settings.
     *
     * capture_mode: set to "manual" when you rely on the authorize -> capture
     * flow for pay-later methods (klarna, billie, in3, riverty). With manual
     * capture those methods reach the "authorized" status and are only captured
     * when you call createShipment()/createShipmentWithTracking(). Leave null
     * for immediate capture (the default).
     */
    'mollie' => [
        'capture_mode' => env('PAYABLE_MOLLIE_CAPTURE_MODE'),
    ],

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
    'actions' => [
        'prepare_callback' => \Marshmallow\Payable\Actions\PrepareForCallback::class,
    ],

    'rules' => [
        'icon_rules' => [200, 200]
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        // 'webhook' => env('STRIPE_WEBHOOK_SECRET'),
        'webhook' => env('STRIPE_PAYABLE_WEBHOOK_SECRET', config('cashier.webhook.secret')),
        'event_types' => [
            'payment_intent.succeeded',
            'payment_intent.requires_action',
            'payment_intent.processing',
            'payment_intent.payment_failed',
            'payment_intent.canceled',
            'payment_intent.amount_capturable_updated',
        ],
        'customer_event_types' => [
            'customer.created',
            'customer.updated',
        ],
    ],

    'multisafepay' => [
        'key' => env('MULTI_SAFE_PAY_KEY')
    ],



    'buckaroo' => [
        'website_key' => env('BUCKAROO_WEBSITE_KEY'),
        'secret' => env('BUCKAROO_SECRET'),
    ],

    /**
     * Worldline Direct needs several credentials rather than a single key, so
     * they are configured here. A multi-tenant consumer (a different account
     * per domain) can override these at runtime the same way it overrides
     * mollie.key, since the provider reads them fresh on every call.
     *
     * api_endpoint is the sandbox host by default; point it at the production
     * host on go-live. webhook_key_id / webhook_secret are the Webhooks API key
     * from the back office, used to verify the signature on incoming events.
     */
    'worldline' => [
        'merchant_id' => env('WORLDLINE_MERCHANT_ID'),
        'api_key_id' => env('WORLDLINE_API_KEY_ID'),
        'api_secret' => env('WORLDLINE_API_SECRET'),
        'api_endpoint' => env('WORLDLINE_API_ENDPOINT', 'https://payment.preprod.direct.worldline-solutions.com'),
        'integrator' => env('WORLDLINE_INTEGRATOR', 'Marshmallow-Payable'),
        'webhook_key_id' => env('WORLDLINE_WEBHOOK_KEY_ID'),
        'webhook_secret' => env('WORLDLINE_WEBHOOK_SECRET'),
    ],
];
