<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\DefaultAddressRequest;
use App\Models\Address;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DefaultAddressController extends Controller
{
    /**
     * Make the given address the user's default delivery address.
     */
    public function __invoke(DefaultAddressRequest $request, Address $address): RedirectResponse
    {
        DB::transaction(function () use ($request, $address): void {
            $request->user()
                ->addresses()
                ->whereKeyNot($address->getKey())
                ->update(['is_default' => false]);

            // is_default is not mass assignable, so it is set explicitly.
            $address->is_default = true;
            $address->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Default address updated.')]);

        return to_route('addresses.index');
    }
}
