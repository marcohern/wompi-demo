import { Head, Link } from '@inertiajs/react';
import { CreditCard } from 'lucide-react';
import CartItemRow from '@/components/cart-item-row';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { formatPrice } from '@/lib/utils';
import { index as cart } from '@/routes/cart';
import { show as checkout } from '@/routes/checkout';
import { index as products } from '@/routes/products';
import type { CartItem } from '@/types';

export default function CartIndex({
    items,
    total,
}: {
    items: CartItem[];
    total: number;
}) {
    return (
        <>
            <Head title="Cart" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    title="Cart"
                    description="Review the items in your cart before checking out"
                />

                {items.length === 0 ? (
                    <div className="flex flex-col items-center gap-4 rounded-xl border border-sidebar-border/70 p-8 text-center dark:border-sidebar-border">
                        <p className="text-sm text-muted-foreground">
                            Your cart is empty.
                        </p>

                        <Button variant="outline" asChild>
                            <Link href={products()}>Browse products</Link>
                        </Button>
                    </div>
                ) : (
                    <>
                        <div className="flex flex-col gap-3">
                            {items.map((item) => (
                                <CartItemRow key={item.id} item={item} />
                            ))}
                        </div>

                        <div className="flex flex-wrap items-center justify-end gap-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                            <span className="text-sm text-muted-foreground">
                                Total
                            </span>

                            <span className="text-xl font-semibold tabular-nums">
                                {formatPrice(total)}
                            </span>

                            <Button asChild>
                                <Link href={checkout()}>
                                    <CreditCard />
                                    Checkout
                                </Link>
                            </Button>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

CartIndex.layout = {
    breadcrumbs: [
        {
            title: 'Cart',
            href: cart(),
        },
    ],
};
