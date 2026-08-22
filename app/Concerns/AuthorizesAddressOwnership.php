<?php

namespace App\Concerns;

use App\Models\Address;

trait AuthorizesAddressOwnership
{
    /**
     * Determine if the user owns the address being acted upon.
     */
    public function authorize(): bool
    {
        $address = $this->route('address');

        return $address instanceof Address
            && $address->user_id === $this->user()->id;
    }
}
