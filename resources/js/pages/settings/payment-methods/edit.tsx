import { Head } from '@inertiajs/react';
import PaymentMethodController from '@/actions/App/Http/Controllers/Settings/PaymentMethodController';
import Heading from '@/components/heading';
import PaymentMethodForm from '@/components/payment-method-form';
import { index } from '@/routes/payment-methods';
import type { PaymentMethod } from '@/types';

export default function EditPaymentMethod({
    paymentMethod,
}: {
    paymentMethod: PaymentMethod;
}) {
    return (
        <>
            <Head title="Edit payment method" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Edit payment method"
                    description="Update the name on the card or its expiry date"
                />

                <PaymentMethodForm
                    action={PaymentMethodController.update.form(
                        paymentMethod.id,
                    )}
                    paymentMethod={paymentMethod}
                    submitLabel="Save changes"
                />
            </div>
        </>
    );
}

EditPaymentMethod.layout = {
    breadcrumbs: [
        {
            title: 'Payment methods',
            href: index(),
        },
    ],
};
