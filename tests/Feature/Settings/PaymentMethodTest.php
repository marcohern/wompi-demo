<?php

use App\Enums\CardBrand;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

const TEST_VISA = '4242424242424242';

/**
 * @return array<string, mixed>
 */
function cardPayload(array $overrides = []): array
{
    return array_merge([
        'number' => TEST_VISA,
        'holder_name' => 'Ada Lovelace',
        'expiry_month' => 6,
        'expiry_year' => (int) date('Y') + 2,
    ], $overrides);
}

test('guests are redirected from every payment method route', function () {
    $method = PaymentMethod::factory()->create();

    $this->get(route('payment-methods.index'))->assertRedirect(route('login'));
    $this->get(route('payment-methods.create'))->assertRedirect(route('login'));
    $this->post(route('payment-methods.store'), cardPayload())->assertRedirect(route('login'));
    $this->get(route('payment-methods.edit', $method))->assertRedirect(route('login'));
    $this->put(route('payment-methods.update', $method), cardPayload())->assertRedirect(route('login'));
    $this->delete(route('payment-methods.destroy', $method))->assertRedirect(route('login'));
    $this->patch(route('payment-methods.default', $method))->assertRedirect(route('login'));

    expect(PaymentMethod::count())->toBe(1);
});

test('the list only shows the users own cards', function () {
    $user = User::factory()->create();
    $own = PaymentMethod::factory()->create(['user_id' => $user->id]);
    PaymentMethod::factory()->create();

    $this->actingAs($user)
        ->get(route('payment-methods.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/payment-methods/index')
            ->has('paymentMethods', 1)
            ->where('paymentMethods.0.id', $own->id),
        );
});

test('a card can be saved with the brand and last four derived', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('payment-methods.store'), cardPayload())
        ->assertRedirect(route('payment-methods.index'));

    $method = $user->paymentMethods()->sole();

    expect($method->brand)->toBe(CardBrand::Visa)
        ->and($method->last_four)->toBe('4242')
        ->and($method->holder_name)->toBe('Ada Lovelace')
        ->and($method->expiry_month)->toBe(6);
});

test('a formatted card number is accepted and normalised', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('payment-methods.store'), cardPayload(['number' => '4242 4242 4242 4242']));

    expect($user->paymentMethods()->sole()->number)->toBe(TEST_VISA);
});

test('the card number is encrypted at rest', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('payment-methods.store'), cardPayload());

    $stored = DB::table('payment_methods')->value('number');

    expect($stored)->not->toBe(TEST_VISA)
        ->and($stored)->not->toContain('4242424242424242')
        ->and(Crypt::decryptString($stored))->toBe(TEST_VISA);
});

test('the full card number is never sent to the browser', function () {
    $user = User::factory()->create();
    $method = PaymentMethod::factory()->create([
        'user_id' => $user->id,
        'number' => TEST_VISA,
    ]);

    $index = $this->actingAs($user)->get(route('payment-methods.index'));
    $edit = $this->actingAs($user)->get(route('payment-methods.edit', $method));

    expect($index->getContent())->not->toContain(TEST_VISA)
        ->and($edit->getContent())->not->toContain(TEST_VISA);

    $index->assertInertia(fn (Assert $page) => $page->missing('paymentMethods.0.number'));
    $edit->assertInertia(fn (Assert $page) => $page->missing('paymentMethod.number'));
});

test('the model hides the number when serialised', function () {
    $method = PaymentMethod::factory()->create(['number' => TEST_VISA]);

    expect($method->toArray())->not->toHaveKey('number')
        ->and(json_encode($method))->not->toContain(TEST_VISA);
});

test('a card number failing the luhn checksum is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('payment-methods.store'), cardPayload(['number' => '4242424242424243']))
        ->assertSessionHasErrors('number');

    expect(PaymentMethod::count())->toBe(0);
});

test('an unsupported card brand is rejected', function () {
    $user = User::factory()->create();

    // Passes the Luhn checksum but has no recognisable issuer prefix.
    $this->actingAs($user)
        ->post(route('payment-methods.store'), cardPayload(['number' => '1234567812345670']))
        ->assertSessionHasErrors('number');

    expect(PaymentMethod::count())->toBe(0);
});

test('the required card fields are validated', function (string $field) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('payment-methods.store'), cardPayload([$field => '']))
        ->assertSessionHasErrors($field);

    expect(PaymentMethod::count())->toBe(0);
})->with(['number', 'holder_name', 'expiry_month', 'expiry_year']);

test('an expiry date in the past is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('payment-methods.store'), cardPayload([
            'expiry_month' => 1,
            'expiry_year' => (int) date('Y') - 1,
        ]))
        ->assertSessionHasErrors('expiry_year');

    expect(PaymentMethod::count())->toBe(0);
});

test('an out of range expiry month is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('payment-methods.store'), cardPayload(['expiry_month' => 13]))
        ->assertSessionHasErrors('expiry_month');
});

test('the holder name and expiry can be updated', function () {
    $user = User::factory()->create();
    $method = PaymentMethod::factory()->create([
        'user_id' => $user->id,
        'holder_name' => 'Old Name',
    ]);

    $this->actingAs($user)
        ->put(route('payment-methods.update', $method), [
            'holder_name' => 'New Name',
            'expiry_month' => 9,
            'expiry_year' => (int) date('Y') + 3,
        ])
        ->assertRedirect(route('payment-methods.index'));

    expect($method->fresh()->holder_name)->toBe('New Name')
        ->and($method->fresh()->expiry_month)->toBe(9);
});

test('the card number cannot be changed through an update', function () {
    $user = User::factory()->create();
    $method = PaymentMethod::factory()->create([
        'user_id' => $user->id,
        'number' => TEST_VISA,
        'last_four' => '4242',
    ]);

    $this->actingAs($user)->put(route('payment-methods.update', $method), [
        'holder_name' => 'Ada Lovelace',
        'expiry_month' => 6,
        'expiry_year' => (int) date('Y') + 2,
        'number' => '5555555555554444',
        'last_four' => '4444',
        'brand' => 'mastercard',
    ]);

    $fresh = $method->fresh();

    expect($fresh->number)->toBe(TEST_VISA)
        ->and($fresh->last_four)->toBe('4242')
        ->and($fresh->brand)->toBe(CardBrand::Visa);
});

test('a card can be removed', function () {
    $user = User::factory()->create();
    $method = PaymentMethod::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->delete(route('payment-methods.destroy', $method))
        ->assertRedirect(route('payment-methods.index'));

    expect(PaymentMethod::find($method->id))->toBeNull();
});

test('the first card added becomes the default and later ones do not', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('payment-methods.store'), cardPayload());
    $this->actingAs($user)->post(route('payment-methods.store'), cardPayload([
        'number' => '5555555555554444',
    ]));

    $methods = $user->paymentMethods()->oldest('id')->get();

    expect($methods)->toHaveCount(2)
        ->and($methods[0]->is_default)->toBeTrue()
        ->and($methods[1]->is_default)->toBeFalse();
});

test('a card can be made the default and the previous one is unset', function () {
    $user = User::factory()->create();
    $current = PaymentMethod::factory()->default()->create(['user_id' => $user->id]);
    $next = PaymentMethod::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->patch(route('payment-methods.default', $next))
        ->assertRedirect(route('payment-methods.index'));

    expect($next->fresh()->is_default)->toBeTrue()
        ->and($current->fresh()->is_default)->toBeFalse();
});

test('deleting the default card promotes the oldest remaining one', function () {
    $user = User::factory()->create();
    $default = PaymentMethod::factory()->default()->create(['user_id' => $user->id]);
    $second = PaymentMethod::factory()->create(['user_id' => $user->id]);
    $third = PaymentMethod::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->delete(route('payment-methods.destroy', $default));

    expect($second->fresh()->is_default)->toBeTrue()
        ->and($third->fresh()->is_default)->toBeFalse();
});

test('is_default cannot be set through mass assignment', function () {
    $user = User::factory()->create();
    PaymentMethod::factory()->default()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('payment-methods.store'), cardPayload(['is_default' => true]));

    expect($user->paymentMethods()->latest('id')->first()->is_default)->toBeFalse();
});

test('an expired card is reported as expired', function () {
    $user = User::factory()->create();
    PaymentMethod::factory()->expired()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('payment-methods.index'))
        ->assertInertia(fn (Assert $page) => $page->where('paymentMethods.0.is_expired', true));
});

test('a user cannot act on another users card', function () {
    $user = User::factory()->create();
    $other = PaymentMethod::factory()->create(['holder_name' => 'Someone Else']);

    $this->actingAs($user)->get(route('payment-methods.edit', $other))->assertForbidden();
    $this->actingAs($user)->put(route('payment-methods.update', $other), [
        'holder_name' => 'Hijacked',
        'expiry_month' => 6,
        'expiry_year' => (int) date('Y') + 2,
    ])->assertForbidden();
    $this->actingAs($user)->patch(route('payment-methods.default', $other))->assertForbidden();
    $this->actingAs($user)->delete(route('payment-methods.destroy', $other))->assertForbidden();

    expect($other->fresh())->not->toBeNull()
        ->and($other->fresh()->holder_name)->toBe('Someone Else');
});
