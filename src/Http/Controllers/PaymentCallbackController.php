<?php

namespace Marshmallow\Payable\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Marshmallow\Payable\Models\Payment;
use Marshmallow\Payable\Facades\Payable;
use Marshmallow\Payable\Actions\PrepareForCallback;

class PaymentCallbackController extends Controller
{
    public function return($payment_id, Request $request): RedirectResponse
    {
        $payment = $this->guard($payment_id, $request);
        $provider = Payable::getProvider($payment->type);
        return $provider->handleReturn($payment, $request);
    }

    public function webhook($payment_id, Request $request): JsonResponse
    {
        $payment = $this->guard($payment_id, $request);
        $provider = Payable::getProvider($payment->type);
        return $provider->handleWebhook($payment, $request);
    }

    public function stripe(Request $request)
    {
        $payment_provider = config('payable.models.payment_provider')::type(\Marshmallow\Payable\Payable::STRIPE)->first();
        $provider = Payable::getProvider($payment_provider->types->first());

        $payment = $provider->guard($request);

        return $provider->handleWebhook($payment, $request);
    }


    protected function guard($payment_id, Request $request): Payment
    {
        $payment = config('payable.models.payment')::find($payment_id);
        if (!$payment) {
            abort(404);
        }

        /**
         * This method is called and does nothing within the package.
         * We've added this for the situation when you use test payments on
         * and IP lock. By overriding this method the package user is able
         * to activate the test api if this is a test payment.
         */
        if (class_exists(config('payable.actions.prepare_callback'))) {
            $payment = config('payable.actions.prepare_callback')::handle($payment);
        }

        $payment->logCallback($request);

        return $payment;
    }
}
