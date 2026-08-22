<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cart\CartItemDestroyRequest;
use App\Http\Requests\Cart\CartItemStoreRequest;
use App\Http\Requests\Cart\CartItemUpdateRequest;
use App\Models\CartItem;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CartItemController extends Controller
{
    /**
     * Add a product to the authenticated user's cart.
     */
    public function store(CartItemStoreRequest $request): RedirectResponse
    {
        $cart = $request->user()->cart()->firstOrCreate();

        $item = $cart->items()->firstOrNew([
            'product_id' => $request->validated('product_id'),
        ]);

        $currentQuantity = $item->exists ? $item->quantity : 0;

        $item->quantity = min(
            $currentQuantity + $request->quantity(),
            $request->maximumQuantity(),
        );

        $item->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Added to cart.')]);

        return back();
    }

    /**
     * Update the quantity of a cart item.
     */
    public function update(CartItemUpdateRequest $request, CartItem $cartItem): RedirectResponse
    {
        $cartItem->update([
            'quantity' => $request->validated('quantity'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cart updated.')]);

        return back();
    }

    /**
     * Remove a cart item.
     */
    public function destroy(CartItemDestroyRequest $request, CartItem $cartItem): RedirectResponse
    {
        $cartItem->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item removed from cart.')]);

        return back();
    }
}
