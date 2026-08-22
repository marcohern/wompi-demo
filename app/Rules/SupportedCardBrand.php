<?php

namespace App\Rules;

use App\Enums\CardBrand;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class SupportedCardBrand implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * Rejects card numbers whose issuer prefix maps to no known brand.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || CardBrand::detect($value) === null) {
            $fail('The card brand is not supported.');
        }
    }
}
