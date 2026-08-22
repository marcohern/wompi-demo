<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    /**
     * Display a listing of the products.
     */
    public function index(): Response
    {
        return Inertia::render('products/index', [
            'products' => Product::query()
                ->latest('id')
                ->get()
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'title' => $product->title,
                    'price' => (float) $product->price,
                    'image_url' => $product->image_url,
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Display the given product.
     */
    public function show(Product $product): Response
    {
        return Inertia::render('products/show', [
            'product' => [
                'id' => $product->id,
                'title' => $product->title,
                'price' => (float) $product->price,
                'image_url' => $product->image_url,
            ],
        ]);
    }
}
