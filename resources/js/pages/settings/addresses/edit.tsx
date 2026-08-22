import { Head } from '@inertiajs/react';
import AddressController from '@/actions/App/Http/Controllers/Settings/AddressController';
import AddressForm from '@/components/address-form';
import Heading from '@/components/heading';
import { index } from '@/routes/addresses';
import type { Address, CountryOption } from '@/types';

export default function EditAddress({
    address,
    countries,
}: {
    address: Address;
    countries: CountryOption[];
}) {
    return (
        <>
            <Head title="Edit address" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Edit address"
                    description="Update this delivery address"
                />

                <AddressForm
                    action={AddressController.update.form(address.id)}
                    countries={countries}
                    address={address}
                    submitLabel="Save changes"
                />
            </div>
        </>
    );
}

EditAddress.layout = {
    breadcrumbs: [
        {
            title: 'Addresses',
            href: index(),
        },
    ],
};
