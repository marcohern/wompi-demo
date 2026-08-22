<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AddressDestroyRequest;
use App\Http\Requests\Settings\AddressEditRequest;
use App\Http\Requests\Settings\AddressStoreRequest;
use App\Http\Requests\Settings\AddressUpdateRequest;
use App\Models\Address;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AddressController extends Controller
{
    /**
     * Show the user's delivery addresses.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('settings/addresses/index', [
            'addresses' => $request->user()
                ->addresses()
                ->orderByDesc('is_default')
                ->latest('id')
                ->get()
                ->map(fn (Address $address) => $this->toArray($address))
                ->values()
                ->all(),
        ]);
    }

    /**
     * Show the form for creating a new address.
     */
    public function create(): Response
    {
        return Inertia::render('settings/addresses/create', [
            'countries' => $this->countryOptions(),
        ]);
    }

    /**
     * Store a newly created address.
     */
    public function store(AddressStoreRequest $request): RedirectResponse
    {
        $user = $request->user();

        $address = $user->addresses()->make($request->validated());

        // The first address a user creates becomes their default.
        $address->is_default = ! $user->addresses()->exists();

        $address->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Address added.')]);

        return to_route('addresses.index');
    }

    /**
     * Show the form for editing an address.
     */
    public function edit(AddressEditRequest $request, Address $address): Response
    {
        return Inertia::render('settings/addresses/edit', [
            'address' => $this->toArray($address),
            'countries' => $this->countryOptions(),
        ]);
    }

    /**
     * Update the given address.
     */
    public function update(AddressUpdateRequest $request, Address $address): RedirectResponse
    {
        $address->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Address updated.')]);

        return to_route('addresses.index');
    }

    /**
     * Remove the given address.
     */
    public function destroy(AddressDestroyRequest $request, Address $address): RedirectResponse
    {
        DB::transaction(function () use ($request, $address): void {
            $wasDefault = $address->is_default;

            $address->delete();

            // Keep exactly one default while the user still has addresses.
            // is_default is not mass assignable, so it is set explicitly.
            if ($wasDefault) {
                $promoted = $request->user()->addresses()->oldest('id')->first();

                if ($promoted instanceof Address) {
                    $promoted->is_default = true;
                    $promoted->save();
                }
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Address removed.')]);

        return to_route('addresses.index');
    }

    /**
     * Get the country options for the address form.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function countryOptions(): array
    {
        /** @var array<string, string> $countries */
        $countries = config('countries');

        return collect($countries)
            ->map(fn (string $label, string $value) => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();
    }

    /**
     * Get the front end representation of an address.
     *
     * @return array<string, mixed>
     */
    private function toArray(Address $address): array
    {
        return [
            'id' => $address->id,
            'country' => $address->country,
            'country_name' => $address->countryName(),
            'political_region' => $address->political_region,
            'city' => $address->city,
            'address1' => $address->address1,
            'address2' => $address->address2,
            'zip_code' => $address->zip_code,
            'notes' => $address->notes,
            'is_default' => $address->is_default,
        ];
    }
}
