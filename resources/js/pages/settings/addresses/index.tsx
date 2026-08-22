import { Form, Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import AddressController from '@/actions/App/Http/Controllers/Settings/AddressController';
import DefaultAddressController from '@/actions/App/Http/Controllers/Settings/DefaultAddressController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, edit, index } from '@/routes/addresses';
import type { Address } from '@/types';

function formatLines(address: Address): string[] {
    const region = [address.city, address.political_region, address.zip_code]
        .filter(Boolean)
        .join(', ');

    return [address.address1, address.address2, region, address.country_name]
        .filter((line): line is string => Boolean(line))
        .filter((line) => line.length > 0);
}

export default function AddressIndex({ addresses }: { addresses: Address[] }) {
    return (
        <>
            <Head title="Addresses" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Addresses"
                    description="Manage where your products are delivered"
                />

                {addresses.length === 0 ? (
                    <div className="flex flex-col items-center gap-4 rounded-lg border border-sidebar-border/70 p-8 text-center dark:border-sidebar-border">
                        <p className="text-sm text-muted-foreground">
                            You have no delivery addresses yet.
                        </p>

                        <Button asChild>
                            <Link href={create()}>
                                <Plus />
                                Add address
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {addresses.map((address) => (
                            <div
                                key={address.id}
                                className="space-y-3 rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                            >
                                {address.is_default && (
                                    <Badge variant="secondary">Default</Badge>
                                )}

                                <div className="text-sm">
                                    {formatLines(address).map((line) => (
                                        <p key={line}>{line}</p>
                                    ))}
                                </div>

                                {address.notes && (
                                    <p className="text-sm text-muted-foreground">
                                        {address.notes}
                                    </p>
                                )}

                                <div className="flex flex-wrap items-center gap-2">
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={edit(address.id)}>
                                            Edit
                                        </Link>
                                    </Button>

                                    {!address.is_default && (
                                        <Form
                                            {...DefaultAddressController.form(
                                                address.id,
                                            )}
                                            options={{ preserveScroll: true }}
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    type="submit"
                                                    variant="outline"
                                                    size="sm"
                                                    disabled={processing}
                                                >
                                                    Set as default
                                                </Button>
                                            )}
                                        </Form>
                                    )}

                                    <Form
                                        {...AddressController.destroy.form(
                                            address.id,
                                        )}
                                        options={{ preserveScroll: true }}
                                    >
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                variant="ghost"
                                                size="sm"
                                                className="text-red-600 hover:text-red-600 dark:text-red-400 dark:hover:text-red-400"
                                                disabled={processing}
                                            >
                                                Delete
                                            </Button>
                                        )}
                                    </Form>
                                </div>
                            </div>
                        ))}

                        <Button variant="outline" asChild>
                            <Link href={create()}>
                                <Plus />
                                Add address
                            </Link>
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}

AddressIndex.layout = {
    breadcrumbs: [
        {
            title: 'Addresses',
            href: index(),
        },
    ],
};
