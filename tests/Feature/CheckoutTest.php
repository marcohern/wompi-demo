<?php

use App\Enums\OrderStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\WompiGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config()->set('wompi.checkout_url', 'https://checkout.co.uat.wompi.dev/p/');
    config()->set('wompi.public_key', 'pub_stagtest_public');
    config()->set('wompi.integrity_secret', 'stagtest_integrity_secret');
    config()->set('wompi.events_secret', 'stagtest_events_secret');
    config()->set('wompi.currency', 'COP');
    config()->set('wompi.redirect_url', null);

    // The test suite's default host is "localhost", which Wompi's WAF rejects.
    // Serve these tests from the same shape of hostname the app is browsed at.
    URL::forceRootUrl('http://wompi.localhost:8000');

    // Inertia SSR would otherwise call the dev server over HTTP, which both
    // slows these tests down and trips the stray request guard below.
    config()->set('inertia.ssr.enabled', false);

    // Any unfaked outbound call is a bug: no test here may reach Wompi.
    Http::preventStrayRequests();
});

/**
 * Give the user a cart holding one product at the given price and quantity.
 */
function cartWith(User $user, float $price = 100_000, int $quantity = 2): CartItem
{
    $cart = Cart::factory()->create(['user_id' => $user->id]);
    $product = Product::factory()->create(['price' => $price, 'title' => 'Test Product']);

    return CartItem::factory()->create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => $quantity,
    ]);
}

/**
 * @return array<string, mixed>
 */
function transactionResponse(string $reference, string $status = 'APPROVED'): array
{
    return [
        'data' => [
            'id' => 'txn-123',
            'reference' => $reference,
            'status' => $status,
            'amount_in_cents' => 20_000_000,
            'currency' => 'COP',
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function eventFor(string $reference, string $status = 'APPROVED'): array
{
    $payload = [
        'event' => 'transaction.updated',
        'data' => [
            'transaction' => [
                'id' => 'txn-123',
                'status' => $status,
                'amount_in_cents' => 20_000_000,
                'reference' => $reference,
            ],
        ],
        'signature' => [
            'properties' => ['transaction.id', 'transaction.status', 'transaction.amount_in_cents'],
        ],
        'timestamp' => 1530291411,
    ];

    $payload['signature']['checksum'] = hash(
        'sha256',
        'txn-123'.$status.'200000001530291411'.config('wompi.events_secret'),
    );

    return $payload;
}

test('guests are redirected from the checkout routes', function () {
    $this->get(route('checkout.show'))->assertRedirect(route('login'));
    $this->post(route('checkout.pay'))->assertRedirect(route('login'));
    $this->get(route('checkout.result'))->assertRedirect(route('login'));
});

test('the checkout page shows the cart contents and total', function () {
    $user = User::factory()->create();
    cartWith($user, 100_000, 2);

    $this->actingAs($user)
        ->get(route('checkout.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('checkout/index')
            ->has('items', 1)
            ->where('items.0.title', 'Test Product')
            ->where('items.0.quantity', 2)
            ->where('items.0.line_total', 200000)
            ->where('total', 200000)
            ->where('currency', 'COP'),
        );
});

test('checking out with an empty cart redirects to the catalog', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('checkout.show'))
        ->assertRedirect(route('products.index'));

    $this->actingAs($user)
        ->post(route('checkout.pay'))
        ->assertRedirect(route('products.index'));

    expect(Order::count())->toBe(0);
});

test('paying creates a pending order with a snapshot of the cart', function () {
    $user = User::factory()->create();
    cartWith($user, 100_000, 2);

    $this->actingAs($user)->post(route('checkout.pay'));

    $order = $user->orders()->sole();

    expect($order->status)->toBe(OrderStatus::Pending)
        ->and($order->currency)->toBe('COP')
        ->and($order->amount_in_cents)->toBe(20_000_000)
        ->and($order->reference)->not->toBeEmpty()
        ->and($order->items)->toHaveCount(1);

    $item = $order->items->first();

    expect($item->title)->toBe('Test Product')
        ->and((float) $item->unit_price)->toBe(100000.0)
        ->and($item->quantity)->toBe(2);
});

/*
 | Wompi's Web Checkout drops any query parameter outside its documented set,
 | so "reference" is the only field that comes back on a webhook event. The
 | order id is embedded in it to keep every event traceable to an order.
 */
test('the payment reference carries the order id', function () {
    $user = User::factory()->create();
    cartWith($user, 100_000, 2);

    $this->actingAs($user)->post(route('checkout.pay'));

    $order = $user->orders()->sole();

    expect($order->reference)->toMatch('/^WOMPI-'.$order->id.'-[A-Z0-9]{8}$/')
        ->and(Order::idFromReference($order->reference))->toBe($order->id)
        ->and($order->reference)->not->toStartWith(Order::PLACEHOLDER_PREFIX);
});

test('a reference outside our format yields no order id', function (string $reference) {
    expect(Order::idFromReference($reference))->toBeNull();
})->with([
    'foreign' => 'not-ours',
    'placeholder' => Order::PLACEHOLDER_PREFIX.'0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b',
    'non numeric id' => 'WOMPI-ABC-ABCD1234',
    'empty' => '',
]);

test('the order snapshot survives a later price change', function () {
    $user = User::factory()->create();
    $cartItem = cartWith($user, 100_000, 2);

    $this->actingAs($user)->post(route('checkout.pay'));

    $cartItem->product->update(['price' => 999_999]);

    $order = $user->orders()->sole();

    expect((float) $order->items->first()->unit_price)->toBe(100000.0)
        ->and($order->amount_in_cents)->toBe(20_000_000);
});

test('paying redirects to wompi with a valid signature', function () {
    $user = User::factory()->create();
    cartWith($user, 100_000, 2);

    $response = $this->actingAs($user)->post(route('checkout.pay'));

    $order = $user->orders()->sole();
    $target = $response->headers->get('Location');

    expect($target)->toStartWith('https://checkout.co.uat.wompi.dev/p/?');

    parse_str((string) parse_url((string) $target, PHP_URL_QUERY), $query);

    expect($query['public-key'])->toBe('pub_stagtest_public')
        ->and($query['currency'])->toBe('COP')
        ->and($query['amount-in-cents'])->toBe('20000000')
        ->and($query['reference'])->toBe($order->reference)
        ->and($query['redirect-url'])->toBe(route('checkout.result').'?order_id='.$order->id)
        ->and($query['signature:integrity'])->toBe(
            hash('sha256', $order->reference.'20000000'.'COP'.'stagtest_integrity_secret'),
        );
});

/*
 | The pay button is an Inertia <Form>, so the browser submits over XHR. An
 | ordinary 302 to an external host cannot be followed by an XHR visit, which
 | is why the button appeared to do nothing. Inertia requires a 409 carrying
 | X-Inertia-Location so the client performs a window.location visit instead.
 |
 | The plain-302 test above still passes because it sends no X-Inertia header,
 | which is exactly how this shipped broken. These tests drive the real path.
 */
test('an inertia pay request returns a location redirect to wompi', function () {
    $user = User::factory()->create();
    cartWith($user, 100_000, 2);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true'])
        ->post(route('checkout.pay'));

    $response->assertStatus(409);

    $order = $user->orders()->sole();
    $target = $response->headers->get('X-Inertia-Location');

    expect($target)->toStartWith('https://checkout.co.uat.wompi.dev/p/?');

    parse_str((string) parse_url((string) $target, PHP_URL_QUERY), $query);

    expect($query['public-key'])->toBe('pub_stagtest_public')
        ->and($query['currency'])->toBe('COP')
        ->and($query['amount-in-cents'])->toBe('20000000')
        ->and($query['reference'])->toBe($order->reference)
        ->and($query['redirect-url'])->toBe(route('checkout.result').'?order_id='.$order->id)
        ->and($query['signature:integrity'])->toBe(
            hash('sha256', $order->reference.'20000000'.'COP'.'stagtest_integrity_secret'),
        );
});

/*
 | Wompi puts Web Checkout behind a WAF that blocks any redirect-url naming a
 | loopback or private-network host and answers with a bare CloudFront 403.
 | Running the app at http://localhost:8000 produced exactly that, so the
 | gateway rejects such an address up front instead of letting Wompi do it.
 */
test('a loopback redirect url is rejected before reaching wompi', function (string $redirectUrl) {
    $order = Order::factory()->create([
        'reference' => 'WOMPI-TEST',
        'amount_in_cents' => 20_000_000,
        'currency' => 'COP',
    ]);

    expect(fn () => app(WompiGateway::class)->checkoutUrl($order, $redirectUrl))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'localhost' => 'http://localhost:8000/checkout/result',
    'loopback ip' => 'http://127.0.0.1:8000/checkout/result',
    'private lan ip' => 'http://192.168.1.50:8000/checkout/result',
    'no host' => '/checkout/result',
]);

test('a non loopback redirect url is accepted', function (string $redirectUrl) {
    $order = Order::factory()->create([
        'reference' => 'WOMPI-TEST',
        'amount_in_cents' => 20_000_000,
        'currency' => 'COP',
    ]);

    $target = app(WompiGateway::class)->checkoutUrl($order, $redirectUrl);

    parse_str((string) parse_url($target, PHP_URL_QUERY), $query);

    expect($query['redirect-url'])->toBe($redirectUrl.'?order_id='.$order->id);
})->with([
    // A subdomain of localhost resolves to 127.0.0.1 in the browser but passes
    // the WAF, which is why the app is browsed there rather than at localhost.
    'localhost subdomain' => 'http://wompi.localhost:8000/checkout/result',
    'tunnel' => 'https://abc123.ngrok-free.app/checkout/result',
]);

test('the configured redirect url overrides the route', function () {
    config()->set('wompi.redirect_url', 'https://abc123.ngrok-free.app/checkout/result');

    $user = User::factory()->create();
    cartWith($user, 100_000, 2);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true'])
        ->post(route('checkout.pay'));

    $order = $user->orders()->sole();
    $target = $response->headers->get('X-Inertia-Location');

    parse_str((string) parse_url((string) $target, PHP_URL_QUERY), $query);

    expect($query['redirect-url'])->toBe('https://abc123.ngrok-free.app/checkout/result?order_id='.$order->id)
        ->and($query['redirect-url'])->not->toStartWith(route('checkout.result'));
});

test('an inertia pay request still records the order and its snapshot', function () {
    $user = User::factory()->create();
    cartWith($user, 100_000, 2);

    $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true'])
        ->post(route('checkout.pay'));

    $order = $user->orders()->sole();

    expect($order->status)->toBe(OrderStatus::Pending)
        ->and($order->amount_in_cents)->toBe(20_000_000)
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->title)->toBe('Test Product');
});

test('an inertia pay request with an empty cart redirects instead of leaving the app', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true'])
        ->post(route('checkout.pay'))
        ->assertRedirect(route('products.index'));

    expect(Order::count())->toBe(0);
});

test('an approved return marks the order paid and empties the cart', function () {
    $user = User::factory()->create();
    cartWith($user, 100_000, 2);

    $this->actingAs($user)->post(route('checkout.pay'));
    $order = $user->orders()->sole();

    Http::fake(['*' => Http::response(transactionResponse($order->reference))]);

    $this->actingAs($user)
        ->get(route('checkout.result', ['id' => 'txn-123', 'order_id' => $order->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('checkout/result')
            ->where('order.status', 'approved')
            ->where('order.is_paid', true),
        );

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Approved)
        ->and($order->paid_at)->not->toBeNull()
        ->and($order->wompi_transaction_id)->toBe('txn-123')
        ->and($user->cart->items()->count())->toBe(0);
});

test('a declined return leaves the cart intact', function () {
    $user = User::factory()->create();
    cartWith($user, 100_000, 2);

    $this->actingAs($user)->post(route('checkout.pay'));
    $order = $user->orders()->sole();

    Http::fake(['*' => Http::response(transactionResponse($order->reference, 'DECLINED'))]);

    $this->actingAs($user)
        ->get(route('checkout.result', ['id' => 'txn-123', 'order_id' => $order->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('order.is_paid', false));

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Declined)
        ->and($order->paid_at)->toBeNull()
        ->and($user->cart->items()->count())->toBe(1);
});

test('another users order cannot be settled through the result page', function () {
    $owner = User::factory()->create();
    cartWith($owner, 100_000, 2);
    $this->actingAs($owner)->post(route('checkout.pay'));
    $order = $owner->orders()->sole();

    $intruder = User::factory()->create();

    Http::fake(['*' => Http::response(transactionResponse($order->reference))]);

    $this->actingAs($intruder)
        ->get(route('checkout.result', ['id' => 'txn-123']))
        ->assertRedirect(route('products.index'));

    // The order id now rides on the redirect URL, so it is guessable. The
    // per-user scope, not the obscurity of the reference, is what stops this.
    $this->actingAs($intruder)
        ->get(route('checkout.result', ['id' => 'txn-123', 'order_id' => $order->id]))
        ->assertRedirect(route('products.index'));

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

test('a result whose order id disagrees with the transaction settles nothing', function () {
    $user = User::factory()->create();
    cartWith($user, 100_000, 2);
    $this->actingAs($user)->post(route('checkout.pay'));
    $order = $user->orders()->sole();

    $other = Order::factory()->create(['user_id' => $user->id]);

    Http::fake(['*' => Http::response(transactionResponse($other->reference))]);

    $this->actingAs($user)
        ->get(route('checkout.result', ['id' => 'txn-123', 'order_id' => $order->id]))
        ->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($other->fresh()->status)->toBe(OrderStatus::Pending);
});

test('a result falls back to the order id when the transaction lookup fails', function () {
    $user = User::factory()->create();
    cartWith($user, 100_000, 2);
    $this->actingAs($user)->post(route('checkout.pay'));
    $order = $user->orders()->sole();

    // The webhook got there first; the customer's return trip then finds Wompi
    // unreachable. The order is still theirs to see.
    $this->postJson(route('wompi.events'), eventFor($order->reference))->assertOk();

    Http::fake(['*' => Http::response([], 500)]);

    $this->actingAs($user)
        ->get(route('checkout.result', ['id' => 'txn-123', 'order_id' => $order->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('checkout/result')
            ->where('order.reference', $order->reference)
            ->where('order.status', 'approved')
            ->where('order.is_paid', true),
        );
});

test('a result for an unknown order id redirects away', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('checkout.result', ['order_id' => 999_999]))
        ->assertRedirect(route('products.index'));
});

test('a result without a transaction id redirects away', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('checkout.result'))
        ->assertRedirect(route('products.index'));
});

test('a webhook with a valid checksum settles the order', function () {
    $user = User::factory()->create();
    cartWith($user, 100_000, 2);
    $this->actingAs($user)->post(route('checkout.pay'));
    $order = $user->orders()->sole();

    $this->postJson(route('wompi.events'), eventFor($order->reference))
        ->assertOk();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Approved)
        ->and($order->paid_at)->not->toBeNull()
        ->and($user->cart->items()->count())->toBe(0);
});

test('a webhook with an invalid checksum changes nothing', function () {
    $user = User::factory()->create();
    cartWith($user, 100_000, 2);
    $this->actingAs($user)->post(route('checkout.pay'));
    $order = $user->orders()->sole();

    $event = eventFor($order->reference);
    $event['signature']['checksum'] = str_repeat('a', 64);

    $this->postJson(route('wompi.events'), $event)->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($user->cart->items()->count())->toBe(1);
});

test('a replayed webhook does not move the payment timestamp', function () {
    $user = User::factory()->create();
    cartWith($user, 100_000, 2);
    $this->actingAs($user)->post(route('checkout.pay'));
    $order = $user->orders()->sole();

    $this->postJson(route('wompi.events'), eventFor($order->reference))->assertOk();
    $paidAt = $order->fresh()->paid_at;

    $this->travel(5)->minutes();

    $this->postJson(route('wompi.events'), eventFor($order->reference))->assertOk();

    expect($order->fresh()->paid_at->timestamp)->toBe($paidAt->timestamp);
});

test('a webhook for an unknown reference is acknowledged', function () {
    $this->postJson(route('wompi.events'), eventFor('WOMPI-does-not-exist'))
        ->assertOk();
});

test('the webhook is exempt from csrf verification', function () {
    $user = User::factory()->create();
    cartWith($user, 100_000, 2);
    $this->actingAs($user)->post(route('checkout.pay'));
    $order = $user->orders()->sole();

    // A plain form post carries no CSRF token; a protected route would 419.
    $this->post(route('wompi.events'), eventFor($order->reference))->assertOk();
});
