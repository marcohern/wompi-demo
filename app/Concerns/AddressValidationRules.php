<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait AddressValidationRules
{
    /**
     * Get the validation rules used to validate delivery addresses.
     *
     * Only the country, city and first address line are required: many
     * countries have no state subdivision or postal code, so requiring
     * those would reject otherwise valid international addresses.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function addressRules(): array
    {
        /** @var array<string, string> $countries */
        $countries = config('countries');

        return [
            'country' => ['required', 'string', Rule::in(array_keys($countries))],
            'political_region' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'address1' => ['required', 'string', 'max:255'],
            'address2' => ['nullable', 'string', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
