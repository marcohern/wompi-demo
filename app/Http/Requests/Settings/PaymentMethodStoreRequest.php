<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PaymentMethodValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PaymentMethodStoreRequest extends FormRequest
{
    use PaymentMethodValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'number' => $this->cardNumberRules(),
            ...$this->cardDetailRules(),
        ];
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

    /**
     * Get the submitted card number with all formatting removed.
     */
    public function cardNumber(): string
    {
        return preg_replace('/\D/', '', (string) $this->validated('number')) ?? '';
    }
}
