<?php

namespace App\Http\Requests\Settings;

use App\Concerns\AuthorizesPaymentMethodOwnership;
use App\Concerns\PaymentMethodValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PaymentMethodUpdateRequest extends FormRequest
{
    use AuthorizesPaymentMethodOwnership, PaymentMethodValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * The card number is intentionally absent: a stored number is immutable,
     * so replacing a card means removing it and adding a new one.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->cardDetailRules();
    }

    /**
     * Get the validators that run after the primary rules.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return $this->expiryNotInThePast();
    }
}
