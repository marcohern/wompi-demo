<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\DefaultPaymentMethodRequest;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DefaultPaymentMethodController extends Controller
{
    /**
     * Make the given card the user's default payment method.
     */
    public function __invoke(DefaultPaymentMethodRequest $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        DB::transaction(function () use ($request, $paymentMethod): void {
            $request->user()
                ->paymentMethods()
                ->whereKeyNot($paymentMethod->getKey())
                ->update(['is_default' => false]);

            // is_default is not mass assignable, so it is set explicitly.
            $paymentMethod->is_default = true;
            $paymentMethod->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Default payment method updated.')]);

        return to_route('payment-methods.index');
    }
}
