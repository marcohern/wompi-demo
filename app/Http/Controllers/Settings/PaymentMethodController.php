<?php

namespace App\Http\Controllers\Settings;

use App\Enums\CardBrand;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PaymentMethodDestroyRequest;
use App\Http\Requests\Settings\PaymentMethodEditRequest;
use App\Http\Requests\Settings\PaymentMethodStoreRequest;
use App\Http\Requests\Settings\PaymentMethodUpdateRequest;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PaymentMethodController extends Controller
{
    /**
     * Show the user's saved payment methods.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('settings/payment-methods/index', [
            'paymentMethods' => $request->user()
                ->paymentMethods()
                ->orderByDesc('is_default')
                ->latest('id')
                ->get()
                ->map(fn (PaymentMethod $method) => $this->toArray($method))
                ->values()
                ->all(),
        ]);
    }

    /**
     * Show the form for adding a payment method.
     */
    public function create(): Response
    {
        return Inertia::render('settings/payment-methods/create');
    }

    /**
     * Store a newly added payment method.
     */
    public function store(PaymentMethodStoreRequest $request): RedirectResponse
    {
        $user = $request->user();
        $number = $request->cardNumber();

        $method = $user->paymentMethods()->make($request->safe()->only([
            'holder_name',
            'expiry_month',
            'expiry_year',
        ]));

        // Derived server side, so none of these are mass assignable.
        $method->brand = CardBrand::detect($number);
        $method->last_four = substr($number, -4);
        $method->number = $number;
        $method->is_default = ! $user->paymentMethods()->exists();

        $method->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment method added.')]);

        return to_route('payment-methods.index');
    }

    /**
     * Show the form for editing a payment method.
     */
    public function edit(PaymentMethodEditRequest $request, PaymentMethod $paymentMethod): Response
    {
        return Inertia::render('settings/payment-methods/edit', [
            'paymentMethod' => $this->toArray($paymentMethod),
        ]);
    }

    /**
     * Update the given payment method.
     *
     * Only the holder name and expiry may change; the card number itself is
     * immutable, so a replacement card is added as a new payment method.
     */
    public function update(PaymentMethodUpdateRequest $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $paymentMethod->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment method updated.')]);

        return to_route('payment-methods.index');
    }

    /**
     * Remove the given payment method.
     */
    public function destroy(PaymentMethodDestroyRequest $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        DB::transaction(function () use ($request, $paymentMethod): void {
            $wasDefault = $paymentMethod->is_default;

            $paymentMethod->delete();

            // Keep exactly one default while the user still has cards.
            // is_default is not mass assignable, so it is set explicitly.
            if ($wasDefault) {
                $promoted = $request->user()->paymentMethods()->oldest('id')->first();

                if ($promoted instanceof PaymentMethod) {
                    $promoted->is_default = true;
                    $promoted->save();
                }
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment method removed.')]);

        return to_route('payment-methods.index');
    }

    /**
     * Get the front end representation of a payment method.
     *
     * The card number is deliberately absent: it never leaves the server.
     *
     * @return array<string, mixed>
     */
    private function toArray(PaymentMethod $method): array
    {
        return [
            'id' => $method->id,
            'brand' => $method->brand->value,
            'brand_label' => $method->brand->label(),
            'last_four' => $method->last_four,
            'masked_number' => $method->maskedNumber(),
            'holder_name' => $method->holder_name,
            'expiry_month' => $method->expiry_month,
            'expiry_year' => $method->expiry_year,
            'is_default' => $method->is_default,
            'is_expired' => $method->isExpired(),
        ];
    }
}
