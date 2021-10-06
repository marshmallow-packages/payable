<?php

namespace Marshmallow\Payable\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Marshmallow\Payable\Models\Payment;
use Marshmallow\Payable\Facades\Payable;
use Marshmallow\Payable\Providers\Adyen;

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

    public function paypal(Request $request)
    {
        mail('stef@marshmallow.dev', 'PayPal callback', json_encode($request->all()));
    }

    public function adyen(Request $request)
    {
        $provider = new Adyen;
        // dd($request->all());
        $payment_provider = config('payable.models.payment_provider')::type(\Marshmallow\Payable\Payable::ADYEN)->first();
        $notification_items = $request->notificationItems;

        dd($notification_items);

        if ($notification_items && count($notification_items)) {

            foreach ($notification_items as $notification_item) {
                if (!array_key_exists('additionalData', $notification_item['NotificationRequestItem'])) {
                    continue;
                }
                $provider_id = $notification_item['NotificationRequestItem']['additionalData']['paymentLinkId'] ?? null;

                if ($provider_id) {

                    $payment = config('payable.models.payment')::where('provider_id', $provider_id)
                        ->where('payment_provider_id', $payment_provider->id)
                        ->first();

                    $provider->handleWebhook($payment, $request);
                }
            }
        }

        return '[accepted]';
    }

    protected function guard($payment_id, Request $request): Payment
    {
        $payment = config('payable.models.payment')::find($payment_id);
        if (!$payment) {
            abort(404);
        }

        $payment->logCallback($request);

        return $payment;
    }
}
