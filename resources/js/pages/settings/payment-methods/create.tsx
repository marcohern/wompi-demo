import { Head } from '@inertiajs/react';
import PaymentMethodController from '@/actions/App/Http/Controllers/Settings/PaymentMethodController';
import Heading from '@/components/heading';
import PaymentMethodForm from '@/components/payment-method-form';
import { index } from '@/routes/payment-methods';

export default function CreatePaymentMethod() {
    return (
        <>
            <Head title="Add payment method" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Add payment method"
                    description="Save a card for faster checkout"
                />

                <PaymentMethodForm
                    action={PaymentMethodController.store.form()}
                    submitLabel="Save card"
                />
            </div>
        </>
    );
}

CreatePaymentMethod.layout = {
    breadcrumbs: [
        {
            title: 'Payment methods',
            href: index(),
        },
    ],
};
