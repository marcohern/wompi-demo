<?php

namespace App\Http\Requests\Settings;

use App\Concerns\AuthorizesPaymentMethodOwnership;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DefaultPaymentMethodRequest extends FormRequest
{
    use AuthorizesPaymentMethodOwnership;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
