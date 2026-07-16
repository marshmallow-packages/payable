<?php

use Illuminate\Support\Facades\Route;
use Marshmallow\Payable\Http\Controllers\PaymentCallbackController;

Route::any('/payment/return/{payment}', [PaymentCallbackController::class, 'return'])->name('payable.return');
Route::post('/payment/webhook/{payment}', [PaymentCallbackController::class, 'webhook'])->name('payable.webhook');
Route::post('/stripe/webhook', [PaymentCallbackController::class, 'stripe'])->name('payable.stripe.webhook');
Route::post('/worldline/webhook', [PaymentCallbackController::class, 'worldline'])->name('payable.worldline.webhook');
