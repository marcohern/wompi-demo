<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    /**
     * Show the contents of the authenticated user's cart.
     */
    public function index(Request $request): Response
    {
        $items = $request->user()->cartItems();

        return Inertia::render('cart/index', [
            'items' => $items
                ->map(fn (CartItem $item) => [
                    'id' => $item->id,
                    'quantity' => $item->quantity,
                    'line_total' => $item->lineTotal(),
                    'product' => [
                        'id' => $item->product->id,
                        'title' => $item->product->title,
                        'price' => (float) $item->product->price,
                        'image_url' => $item->product->image_url,
                    ],
                ])
                ->values()
                ->all(),
            'total' => (float) $items->sum(fn (CartItem $item) => $item->lineTotal()),
        ]);
    }
}
