<?php

use App\Models\Product;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected from the product index', function () {
    Product::factory()->create();

    $response = $this->get(route('products.index'));

    $response->assertRedirect(route('login'));
});

test('guests are redirected from the product details page', function () {
    $product = Product::factory()->create();

    $response = $this->get(route('products.show', $product));

    $response->assertRedirect(route('login'));
});

test('authenticated users can see every product on the index', function () {
    $products = Product::factory(3)->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('products/index')
            ->has('products', 3)
            ->has('products.0', fn (Assert $product) => $product
                ->where('id', $products->last()->id)
                ->where('title', $products->last()->title)
                ->where('price', (float) $products->last()->price)
                ->where('image_url', $products->last()->image_url),
            ),
        );
});

test('the product index renders when there are no products', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('products/index')
            ->has('products', 0),
        );
});

test('authenticated users can view a product details page', function () {
    $product = Product::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('products.show', $product))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('products/show')
            ->where('product.id', $product->id)
            ->where('product.title', $product->title)
            ->where('product.price', (float) $product->price)
            ->where('product.image_url', $product->image_url),
        );
});

test('an unknown product returns a not found response', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('products.show', 999))
        ->assertNotFound();
});
