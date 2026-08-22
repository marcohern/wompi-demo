<?php

namespace App\Concerns;

use App\Rules\Luhn;
use App\Rules\SupportedCardBrand;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

trait PaymentMethodValidationRules
{
    /**
     * Get the validation rules used to validate a card number.
     *
     * The CVV is deliberately never accepted: nothing here authorizes a
     * payment, so there is no legitimate reason to receive or store it.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function cardNumberRules(): array
    {
        return [
            'required',
            'string',
            new Luhn,
            new SupportedCardBrand,
        ];
    }

    /**
     * Get the validation rules shared by the card detail fields.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function cardDetailRules(): array
    {
        return [
            'holder_name' => ['required', 'string', 'max:255'],
            'expiry_month' => ['required', 'integer', 'min:1', 'max:12'],
            'expiry_year' => [
                'required',
                'integer',
                'min:'.date('Y'),
                'max:'.((int) date('Y') + 20),
            ],
        ];
    }

    /**
     * Reject an expiry month and year combination that has already passed.
     *
     * @return array<int, callable>
     */
    protected function expiryNotInThePast(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['expiry_month', 'expiry_year'])) {
                    return;
                }

                $month = (int) $this->input('expiry_month');
                $year = (int) $this->input('expiry_year');

                $expiresAt = Carbon::createFromDate($year, $month, 1)->endOfMonth();

                if ($expiresAt->isPast()) {
                    $validator->errors()->add('expiry_month', 'The card has already expired.');
                }
            },
        ];
    }
}
