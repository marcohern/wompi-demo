import { Head, Link } from '@inertiajs/react';
import { CircleAlert, CircleCheck } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatPrice } from '@/lib/utils';
import { index as cart } from '@/routes/cart';
import { show as checkout } from '@/routes/checkout';
import { index as products } from '@/routes/products';

type OrderResult = {
    reference: string;
    status: string;
    status_label: string;
    is_paid: boolean;
    total: number;
    transaction_id: string | null;
};

export default function CheckoutResult({ order }: { order: OrderResult }) {
    return (
        <>
            <Head title="Payment result" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    title={
                        order.is_paid
                            ? 'Payment approved'
                            : 'Payment not completed'
                    }
                    description={
                        order.is_paid
                            ? 'Thank you. Your order has been paid.'
                            : 'Your order was not paid. You can try again from your cart.'
                    }
                />

                <div className="flex max-w-xl flex-col gap-4 rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <div className="flex items-center gap-2">
                        {order.is_paid ? (
                            <CircleCheck className="size-5 text-green-600 dark:text-green-400" />
                        ) : (
                            <CircleAlert className="size-5 text-red-600 dark:text-red-400" />
                        )}

                        <Badge
                            variant={
                                order.is_paid ? 'secondary' : 'destructive'
                            }
                        >
                            {order.status_label}
                        </Badge>
                    </div>

                    <dl className="grid gap-2 text-sm">
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">Reference</dt>
                            <dd className="font-medium tabular-nums">
                                {order.reference}
                            </dd>
                        </div>

                        <div className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">Total</dt>
                            <dd className="font-medium tabular-nums">
                                {formatPrice(order.total)}
                            </dd>
                        </div>

                        {order.transaction_id && (
                            <div className="flex justify-between gap-4">
                                <dt className="text-muted-foreground">
                                    Transaction
                                </dt>
                                <dd className="font-medium tabular-nums">
                                    {order.transaction_id}
                                </dd>
                            </div>
                        )}
                    </dl>

                    <div className="flex flex-wrap items-center gap-3">
                        <Button asChild>
                            <Link href={products()}>Continue shopping</Link>
                        </Button>

                        {!order.is_paid && (
                            <Button variant="outline" asChild>
                                <Link href={cart()}>Back to cart</Link>
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

CheckoutResult.layout = {
    breadcrumbs: [
        {
            title: 'Checkout',
            href: checkout(),
        },
    ],
};
