<?php

namespace App\Concerns;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait CartValidationRules
{
    /**
     * The largest quantity a single cart line may hold.
     */
    protected int $maximumQuantity = 99;

    /**
     * Get the validation rules used to validate a cart line quantity.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function quantityRules(): array
    {
        return ['required', 'integer', 'min:1', 'max:'.$this->maximumQuantity];
    }

    /**
     * Get the validation rules used to validate the product being added.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function productRules(): array
    {
        return ['required', 'integer', Rule::exists(Product::class, 'id')];
    }
}
