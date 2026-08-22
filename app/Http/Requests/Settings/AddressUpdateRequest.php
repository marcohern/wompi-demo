<?php

namespace App\Http\Requests\Settings;

use App\Concerns\AddressValidationRules;
use App\Concerns\AuthorizesAddressOwnership;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AddressUpdateRequest extends FormRequest
{
    use AddressValidationRules, AuthorizesAddressOwnership;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->addressRules();
    }
}
