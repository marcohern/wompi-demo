import { Head } from '@inertiajs/react';
import AddressController from '@/actions/App/Http/Controllers/Settings/AddressController';
import AddressForm from '@/components/address-form';
import Heading from '@/components/heading';
import { index } from '@/routes/addresses';
import type { CountryOption } from '@/types';

export default function CreateAddress({
    countries,
}: {
    countries: CountryOption[];
}) {
    return (
        <>
            <Head title="Add address" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Add address"
                    description="Add a delivery address to your account"
                />

                <AddressForm
                    action={AddressController.store.form()}
                    countries={countries}
                    submitLabel="Save address"
                />
            </div>
        </>
    );
}

CreateAddress.layout = {
    breadcrumbs: [
        {
            title: 'Addresses',
            href: index(),
        },
    ],
};
