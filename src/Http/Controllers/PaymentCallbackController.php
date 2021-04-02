<?php

namespace Marshmallow\Payable\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Marshmallow\Payable\Models\Payment;
use Marshmallow\Payable\Facades\Payable;

class PaymentCallbackController extends Controller
{
    public function return($payment_id, Request $request): RedirectResponse
    {
        $payment = $this->guard($payment_id, $request);
        $provider = Payable::getProvider();
        return $provider->handleReturn($payment, $request);
    }

    public function webhook($payment_id, Request $request): JsonResponse
    {
        $payment = $this->guard($payment_id, $request);
        $provider = Payable::getProvider();
        return $provider->handleWebhook($payment, $request);
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
