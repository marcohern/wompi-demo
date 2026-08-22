import { Form, Head, Link } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import CheckoutController from '@/actions/App/Http/Controllers/CheckoutController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { formatPrice } from '@/lib/utils';
import { index as cart } from '@/routes/cart';
import { show as checkout } from '@/routes/checkout';

type CheckoutItem = {
    id: number;
    title: string;
    image_url: string;
    unit_price: number;
    quantity: number;
    line_total: number;
};

export default function CheckoutIndex({
    items,
    total,
    currency,
}: {
    items: CheckoutItem[];
    total: number;
    currency: string;
}) {
    return (
        <>
            <Head title="Checkout" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    title="Checkout"
                    description="Review your order, then pay securely with Wompi"
                />

                {/* Read only on purpose: quantities and removals happen in the cart. */}
                <div className="flex flex-col gap-3">
                    {items.map((item) => (
                        <div
                            key={item.id}
                            className="flex flex-wrap items-center gap-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                        >
                            <div className="h-16 w-24 shrink-0 overflow-hidden rounded-lg bg-muted">
                                <img
                                    src={item.image_url}
                                    alt={item.title}
                                    loading="lazy"
                                    className="size-full object-cover"
                                />
                            </div>

                            <div className="min-w-40 flex-1">
                                <p className="font-medium">{item.title}</p>
                                <p className="text-sm text-muted-foreground">
                                    {formatPrice(item.unit_price)} &times;{' '}
                                    {item.quantity}
                                </p>
                            </div>

                            <p className="w-28 text-right font-medium tabular-nums">
                                {formatPrice(item.line_total)}
                            </p>
                        </div>
                    ))}
                </div>

                <div className="flex flex-col gap-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <div className="flex items-center justify-end gap-4">
                        <span className="text-sm text-muted-foreground">
                            Total ({currency})
                        </span>

                        <span className="text-xl font-semibold tabular-nums">
                            {formatPrice(total)}
                        </span>
                    </div>

                    <div className="flex flex-wrap items-center justify-end gap-3">
                        <Button variant="ghost" asChild>
                            <Link href={cart()}>Back to cart</Link>
                        </Button>

                        <Form {...CheckoutController.pay.form()}>
                            {({ processing }) => (
                                <Button type="submit" disabled={processing}>
                                    <Lock />
                                    Pay with Wompi
                                </Button>
                            )}
                        </Form>
                    </div>

                    <p className="text-right text-xs text-muted-foreground">
                        You will be redirected to Wompi to complete the payment.
                    </p>
                </div>
            </div>
        </>
    );
}

CheckoutIndex.layout = {
    breadcrumbs: [
        {
            title: 'Checkout',
            href: checkout(),
        },
    ],
};
