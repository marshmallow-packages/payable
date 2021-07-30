<?php

namespace Marshmallow\Payable\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Marshmallow\Payable\Models\Payment;
use Marshmallow\Payable\Facades\Payable;
use Marshmallow\Payable\Models\PaymentProvider;

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
        $payment_provider = PaymentProvider::type(\Marshmallow\Payable\Payable::STRIPE)->first();
        $provider = Payable::getProvider($payment_provider->types->first());

        $payment = $provider->guard($request);
        return $provider->handleWebhook($payment, $request);
    }

    public function paypal(Request $request)
    {
        mail('stef@marshmallow.dev', 'PayPal callback', json_encode($request->all()));
    }

    protected function guard($payment_id, Request $request): Payment
    {
        $payment = Payment::find($payment_id);
        if (!$payment) {
            abort(404);
        }

        $payment->logCallback($request);

        return $payment;
    }
}
