<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class Luhn implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * Verifies the Luhn (mod 10) checksum that every payment card number
     * satisfies, which catches mistyped digits before anything is stored.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a valid card number.');

            return;
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (! $this->passes($digits)) {
            $fail('The :attribute is not a valid card number.');
        }
    }

    /**
     * Determine if the given digits satisfy the Luhn checksum.
     */
    public function passes(string $digits): bool
    {
        if (strlen($digits) < 12) {
            return false;
        }

        $sum = 0;
        $double = false;

        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $digit = (int) $digits[$i];

            if ($double) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $double = ! $double;
        }

        return $sum % 10 === 0;
    }
}
