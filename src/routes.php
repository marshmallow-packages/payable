<?php

use Illuminate\Support\Facades\Route;
use Marshmallow\Payable\Http\Controllers\PaymentCallbackController;

Route::any('/payment/return/{payment}', [PaymentCallbackController::class, 'return'])->name('payable.return');
Route::post('/payment/webhook/{payment}', [PaymentCallbackController::class, 'webhook'])->name('payable.webhook');
Route::post('/stripe/webhook', [PaymentCallbackController::class, 'stripe'])->name('payable.stripe.webhook');
Route::post('/paypal/webhook', [PaymentCallbackController::class, 'paypal'])->name('payable.paypal.webhook');
