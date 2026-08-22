import { Form, Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import DefaultPaymentMethodController from '@/actions/App/Http/Controllers/Settings/DefaultPaymentMethodController';
import PaymentMethodController from '@/actions/App/Http/Controllers/Settings/PaymentMethodController';
import CardBrandBadge from '@/components/card-brand-badge';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, edit, index } from '@/routes/payment-methods';
import type { PaymentMethod } from '@/types';

export default function PaymentMethodIndex({
    paymentMethods,
}: {
    paymentMethods: PaymentMethod[];
}) {
    return (
        <>
            <Head title="Payment methods" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Payment methods"
                    description="Manage the cards saved to your account"
                />

                {paymentMethods.length === 0 ? (
                    <div className="flex flex-col items-center gap-4 rounded-lg border border-sidebar-border/70 p-8 text-center dark:border-sidebar-border">
                        <p className="text-sm text-muted-foreground">
                            You have no saved payment methods yet.
                        </p>

                        <Button asChild>
                            <Link href={create()}>
                                <Plus />
                                Add payment method
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {paymentMethods.map((method) => (
                            <div
                                key={method.id}
                                className="space-y-3 rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                            >
                                <div className="flex flex-wrap items-center gap-2">
                                    <CardBrandBadge
                                        brand={method.brand}
                                        label={method.brand_label}
                                    />

                                    {method.is_default && (
                                        <Badge variant="secondary">
                                            Default
                                        </Badge>
                                    )}

                                    {method.is_expired && (
                                        <Badge variant="destructive">
                                            Expired
                                        </Badge>
                                    )}
                                </div>

                                <div className="text-sm">
                                    <p className="tabular-nums">
                                        {method.masked_number}
                                    </p>
                                    <p className="text-muted-foreground">
                                        {method.holder_name} &middot; expires{' '}
                                        {String(method.expiry_month).padStart(
                                            2,
                                            '0',
                                        )}
                                        /{method.expiry_year}
                                    </p>
                                </div>

                                <div className="flex flex-wrap items-center gap-2">
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={edit(method.id)}>Edit</Link>
                                    </Button>

                                    {!method.is_default && (
                                        <Form
                                            {...DefaultPaymentMethodController.form(
                                                method.id,
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
                                        {...PaymentMethodController.destroy.form(
                                            method.id,
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
                                Add payment method
                            </Link>
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}

PaymentMethodIndex.layout = {
    breadcrumbs: [
        {
            title: 'Payment methods',
            href: index(),
        },
    ],
};
