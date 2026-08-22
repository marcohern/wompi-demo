<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected from the cart page', function () {
    $this->get(route('cart.index'))->assertRedirect(route('login'));
});

test('every product added is listed on the cart page', function () {
    $user = User::factory()->create();

    $products = Product::factory(3)->sequence(
        ['title' => 'First Product', 'price' => 10_000],
        ['title' => 'Second Product', 'price' => 20_000],
        ['title' => 'Third Product', 'price' => 30_000],
    )->create();

    foreach ($products as $product) {
        $this->actingAs($user)
            ->post(route('cart.items.store'), ['product_id' => $product->id])
            ->assertRedirect();
    }

    $this->actingAs($user)
        ->get(route('cart.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cart/index')
            ->has('items', 3)
            ->where('total', 60_000),
        );

    expect($user->fresh()->cart->items()->pluck('product_id')->all())
        ->toEqualCanonicalizing($products->pluck('id')->all());
});

test('every product added is listed on the checkout page', function () {
    $user = User::factory()->create();
    $products = Product::factory(3)->create();

    foreach ($products as $product) {
        $this->actingAs($user)->post(route('cart.items.store'), ['product_id' => $product->id]);
    }

    $this->actingAs($user)
        ->get(route('checkout.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('checkout/index')
            ->has('items', 3),
        );
});

test('adding a product leaves the products already in the cart alone', function () {
    $user = User::factory()->create();
    $existing = Product::factory(2)->create();
    $added = Product::factory()->create();

    foreach ($existing as $product) {
        $this->actingAs($user)->post(route('cart.items.store'), ['product_id' => $product->id]);
    }

    $this->actingAs($user)->post(route('cart.items.store'), ['product_id' => $added->id]);

    expect($user->fresh()->cart->items()->count())->toBe(3);
});

test('a user cannot own a second cart', function () {
    // A second cart used to be possible, because resolving the cart with
    // firstOrCreate() is read-then-insert. Its items were then invisible on
    // the cart page, which reads a HasOne and so only ever saw the first
    // cart. The database now makes that state unrepresentable.
    $user = User::factory()->create();
    Cart::factory()->create(['user_id' => $user->id]);

    expect(fn () => Cart::factory()->create(['user_id' => $user->id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('adding many products never creates more than one cart', function () {
    $user = User::factory()->create();

    foreach (Product::factory(4)->create() as $product) {
        $this->actingAs($user)->post(route('cart.items.store'), ['product_id' => $product->id]);
    }

    expect(Cart::where('user_id', $user->id)->count())->toBe(1)
        ->and($user->fresh()->cart->items()->count())->toBe(4);
});

test('resolving the current cart twice returns the same cart', function () {
    $user = User::factory()->create();

    expect($user->currentCart()->id)->toBe($user->currentCart()->id)
        ->and(Cart::where('user_id', $user->id)->count())->toBe(1);
});

test('guests cannot add items to a cart', function () {
    $product = Product::factory()->create();

    $this->post(route('cart.items.store'), ['product_id' => $product->id])
        ->assertRedirect(route('login'));

    expect(CartItem::count())->toBe(0);
});

test('guests cannot update or remove cart items', function () {
    $item = CartItem::factory()->create();

    $this->patch(route('cart.items.update', $item), ['quantity' => 5])
        ->assertRedirect(route('login'));

    $this->delete(route('cart.items.destroy', $item))
        ->assertRedirect(route('login'));

    expect($item->fresh()->quantity)->toBe($item->quantity);
});

test('adding a product creates a cart with a single line', function () {
    $product = Product::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('cart.items.store'), ['product_id' => $product->id])
        ->assertRedirect();

    $cart = $user->fresh()->cart;

    expect($cart)->not->toBeNull()
        ->and($cart->items()->count())->toBe(1)
        ->and($cart->items()->first()->product_id)->toBe($product->id)
        ->and($cart->items()->first()->quantity)->toBe(1);
});

test('adding the same product again increments the existing line', function () {
    $product = Product::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('cart.items.store'), ['product_id' => $product->id]);
    $this->actingAs($user)->post(route('cart.items.store'), ['product_id' => $product->id]);

    $cart = $user->fresh()->cart;

    expect($cart->items()->count())->toBe(1)
        ->and($cart->items()->first()->quantity)->toBe(2);
});

test('a quantity may be supplied when adding to the cart', function () {
    $product = Product::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('cart.items.store'), ['product_id' => $product->id, 'quantity' => 3])
        ->assertRedirect();

    expect($user->fresh()->cart->items()->first()->quantity)->toBe(3);
});

test('adding an unknown product fails validation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('cart.items.store'), ['product_id' => 999])
        ->assertSessionHasErrors('product_id');

    expect(CartItem::count())->toBe(0);
});

test('a cart item quantity can be updated', function () {
    $user = User::factory()->create();
    $cart = Cart::factory()->create(['user_id' => $user->id]);
    $item = CartItem::factory()->create(['cart_id' => $cart->id, 'quantity' => 1]);

    $this->actingAs($user)
        ->patch(route('cart.items.update', $item), ['quantity' => 7])
        ->assertRedirect();

    expect($item->fresh()->quantity)->toBe(7);
});

test('a cart item quantity must be at least one', function (int $quantity) {
    $user = User::factory()->create();
    $cart = Cart::factory()->create(['user_id' => $user->id]);
    $item = CartItem::factory()->create(['cart_id' => $cart->id, 'quantity' => 4]);

    $this->actingAs($user)
        ->patch(route('cart.items.update', $item), ['quantity' => $quantity])
        ->assertSessionHasErrors('quantity');

    expect($item->fresh()->quantity)->toBe(4);
})->with([0, -1, 100]);

test('a cart item can be removed', function () {
    $user = User::factory()->create();
    $cart = Cart::factory()->create(['user_id' => $user->id]);
    $item = CartItem::factory()->create(['cart_id' => $cart->id]);

    $this->actingAs($user)
        ->delete(route('cart.items.destroy', $item))
        ->assertRedirect();

    expect(CartItem::find($item->id))->toBeNull();
});

test('a user cannot update another users cart item', function () {
    $user = User::factory()->create();
    $item = CartItem::factory()->create(['quantity' => 2]);

    $this->actingAs($user)
        ->patch(route('cart.items.update', $item), ['quantity' => 9])
        ->assertForbidden();

    expect($item->fresh()->quantity)->toBe(2);
});

test('a user cannot remove another users cart item', function () {
    $user = User::factory()->create();
    $item = CartItem::factory()->create();

    $this->actingAs($user)
        ->delete(route('cart.items.destroy', $item))
        ->assertForbidden();

    expect(CartItem::find($item->id))->not->toBeNull();
});

test('the cart page lists items with line totals and a cart total', function () {
    $user = User::factory()->create();
    $cart = Cart::factory()->create(['user_id' => $user->id]);

    $first = Product::factory()->create(['price' => 10.00]);
    $second = Product::factory()->create(['price' => 2.50]);

    CartItem::factory()->create([
        'cart_id' => $cart->id,
        'product_id' => $first->id,
        'quantity' => 2,
    ]);
    CartItem::factory()->create([
        'cart_id' => $cart->id,
        'product_id' => $second->id,
        'quantity' => 3,
    ]);

    $this->actingAs($user)
        ->get(route('cart.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cart/index')
            ->has('items', 2)
            ->where('items.0.quantity', 2)
            ->where('items.0.line_total', 20)
            ->where('items.0.product.title', $first->title)
            ->where('items.1.line_total', 7.5)
            ->where('total', 27.5),
        );
});

test('the cart page renders when the user has no cart', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('cart.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cart/index')
            ->has('items', 0)
            ->where('total', 0),
        );
});

test('another users cart items are never shown', function () {
    $user = User::factory()->create();
    Cart::factory()->create(['user_id' => $user->id]);
    CartItem::factory()->create();

    $this->actingAs($user)
        ->get(route('cart.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('items', 0));
});
