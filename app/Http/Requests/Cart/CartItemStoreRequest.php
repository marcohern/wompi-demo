<?php

namespace App\Http\Requests\Cart;

use App\Concerns\CartValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CartItemStoreRequest extends FormRequest
{
    use CartValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => $this->productRules(),
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:'.$this->maximumQuantity],
        ];
    }

    /**
     * Get the quantity being added to the cart.
     */
    public function quantity(): int
    {
        return (int) ($this->validated('quantity') ?? 1);
    }

    /**
     * Get the largest quantity a single cart line may hold.
     */
    public function maximumQuantity(): int
    {
        return $this->maximumQuantity;
    }
}
