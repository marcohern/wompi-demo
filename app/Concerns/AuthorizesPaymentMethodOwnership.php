<?php

namespace App\Concerns;

use App\Models\PaymentMethod;

trait AuthorizesPaymentMethodOwnership
{
    /**
     * Determine if the user owns the payment method being acted upon.
     */
    public function authorize(): bool
    {
        $paymentMethod = $this->route('payment_method');

        return $paymentMethod instanceof PaymentMethod
            && $paymentMethod->user_id === $this->user()->id;
    }
}
