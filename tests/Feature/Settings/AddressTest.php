<?php

use App\Models\Address;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array<string, string>
 */
function addressPayload(array $overrides = []): array
{
    return array_merge([
        'country' => 'CO',
        'political_region' => 'Antioquia',
        'city' => 'Medellín',
        'address1' => 'Calle 10 #43-25',
        'address2' => 'Apto 502',
        'zip_code' => '050021',
        'notes' => 'Leave with the doorman.',
    ], $overrides);
}

test('guests are redirected from every address route', function () {
    $address = Address::factory()->create();

    $this->get(route('addresses.index'))->assertRedirect(route('login'));
    $this->get(route('addresses.create'))->assertRedirect(route('login'));
    $this->post(route('addresses.store'), addressPayload())->assertRedirect(route('login'));
    $this->get(route('addresses.edit', $address))->assertRedirect(route('login'));
    $this->put(route('addresses.update', $address), addressPayload())->assertRedirect(route('login'));
    $this->delete(route('addresses.destroy', $address))->assertRedirect(route('login'));
    $this->patch(route('addresses.default', $address))->assertRedirect(route('login'));

    expect(Address::count())->toBe(1);
});

test('the address list only shows the users own addresses', function () {
    $user = User::factory()->create();
    $own = Address::factory()->create(['user_id' => $user->id, 'city' => 'Bogotá']);
    Address::factory()->create(['city' => 'Lima']);

    $this->actingAs($user)
        ->get(route('addresses.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/addresses/index')
            ->has('addresses', 1)
            ->where('addresses.0.id', $own->id)
            ->where('addresses.0.city', 'Bogotá'),
        );
});

test('the create page exposes the country options', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('addresses.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/addresses/create')
            ->has('countries')
            ->where('countries.0.value', 'AF'),
        );
});

test('an address can be created', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('addresses.store'), addressPayload())
        ->assertRedirect(route('addresses.index'));

    $address = $user->addresses()->sole();

    expect($address->country)->toBe('CO')
        ->and($address->political_region)->toBe('Antioquia')
        ->and($address->city)->toBe('Medellín')
        ->and($address->address1)->toBe('Calle 10 #43-25')
        ->and($address->address2)->toBe('Apto 502')
        ->and($address->zip_code)->toBe('050021')
        ->and($address->notes)->toBe('Leave with the doorman.');
});

test('the first address becomes the default and later ones do not', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('addresses.store'), addressPayload());
    $this->actingAs($user)->post(route('addresses.store'), addressPayload(['city' => 'Cali']));

    $addresses = $user->addresses()->oldest('id')->get();

    expect($addresses)->toHaveCount(2)
        ->and($addresses[0]->is_default)->toBeTrue()
        ->and($addresses[1]->is_default)->toBeFalse();
});

test('the optional address fields may be omitted', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('addresses.store'), [
            'country' => 'SG',
            'city' => 'Singapore',
            'address1' => '1 Raffles Place',
        ])
        ->assertRedirect(route('addresses.index'));

    $address = $user->addresses()->sole();

    expect($address->political_region)->toBeNull()
        ->and($address->zip_code)->toBeNull()
        ->and($address->notes)->toBeNull();
});

test('the required address fields are validated', function (string $field) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('addresses.store'), addressPayload([$field => '']))
        ->assertSessionHasErrors($field);

    expect(Address::count())->toBe(0);
})->with(['country', 'city', 'address1']);

test('an unknown country code is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('addresses.store'), addressPayload(['country' => 'ZZ']))
        ->assertSessionHasErrors('country');

    expect(Address::count())->toBe(0);
});

test('an address can be updated', function () {
    $user = User::factory()->create();
    $address = Address::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->put(route('addresses.update', $address), addressPayload(['city' => 'Cartagena']))
        ->assertRedirect(route('addresses.index'));

    expect($address->fresh()->city)->toBe('Cartagena');
});

test('the edit page shows the address and country options', function () {
    $user = User::factory()->create();
    $address = Address::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('addresses.edit', $address))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/addresses/edit')
            ->where('address.id', $address->id)
            ->has('countries'),
        );
});

test('an address can be deleted', function () {
    $user = User::factory()->create();
    $address = Address::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->delete(route('addresses.destroy', $address))
        ->assertRedirect(route('addresses.index'));

    expect(Address::find($address->id))->toBeNull();
});

test('deleting the default address promotes the oldest remaining one', function () {
    $user = User::factory()->create();
    $default = Address::factory()->default()->create(['user_id' => $user->id]);
    $second = Address::factory()->create(['user_id' => $user->id]);
    $third = Address::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->delete(route('addresses.destroy', $default));

    expect($second->fresh()->is_default)->toBeTrue()
        ->and($third->fresh()->is_default)->toBeFalse();
});

test('deleting a non default address does not change the default', function () {
    $user = User::factory()->create();
    $default = Address::factory()->default()->create(['user_id' => $user->id]);
    $other = Address::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->delete(route('addresses.destroy', $other));

    expect($default->fresh()->is_default)->toBeTrue();
});

test('an address can be made the default and the previous one is unset', function () {
    $user = User::factory()->create();
    $current = Address::factory()->default()->create(['user_id' => $user->id]);
    $next = Address::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->patch(route('addresses.default', $next))
        ->assertRedirect(route('addresses.index'));

    expect($next->fresh()->is_default)->toBeTrue()
        ->and($current->fresh()->is_default)->toBeFalse();
});

test('a user cannot act on another users address', function () {
    $user = User::factory()->create();
    $other = Address::factory()->create(['city' => 'Quito']);

    $this->actingAs($user)->get(route('addresses.edit', $other))->assertForbidden();
    $this->actingAs($user)->put(route('addresses.update', $other), addressPayload())->assertForbidden();
    $this->actingAs($user)->patch(route('addresses.default', $other))->assertForbidden();
    $this->actingAs($user)->delete(route('addresses.destroy', $other))->assertForbidden();

    expect($other->fresh())->not->toBeNull()
        ->and($other->fresh()->city)->toBe('Quito');
});

test('is_default cannot be set through mass assignment', function () {
    $user = User::factory()->create();
    Address::factory()->default()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('addresses.store'), addressPayload(['is_default' => true]));

    expect($user->addresses()->latest('id')->first()->is_default)->toBeFalse();
});
