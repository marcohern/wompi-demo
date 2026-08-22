import { Form, Link, router } from '@inertiajs/react';
import { Minus, Plus, Trash2 } from 'lucide-react';
import CartItemController from '@/actions/App/Http/Controllers/CartItemController';
import { Button } from '@/components/ui/button';
import { formatPrice } from '@/lib/utils';
import { show } from '@/routes/products';
import type { CartItem } from '@/types';

const MIN_QUANTITY = 1;
const MAX_QUANTITY = 99;

export default function CartItemRow({ item }: { item: CartItem }) {
    const setQuantity = (quantity: number) => {
        router.patch(
            CartItemController.update.url(item.id),
            { quantity },
            { preserveScroll: true },
        );
    };

    return (
        <div className="flex flex-wrap items-center gap-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <Link
                href={show(item.product.id)}
                className="h-20 w-28 shrink-0 overflow-hidden rounded-lg bg-muted"
                aria-label={item.product.title}
            >
                <img
                    src={item.product.image_url}
                    alt={item.product.title}
                    loading="lazy"
                    className="size-full object-cover"
                />
            </Link>

            <div className="min-w-40 flex-1">
                <Link
                    href={show(item.product.id)}
                    className="font-medium hover:underline"
                >
                    {item.product.title}
                </Link>

                <p className="text-sm text-muted-foreground">
                    {formatPrice(item.product.price)} each
                </p>
            </div>

            <div className="flex items-center gap-1">
                <Button
                    variant="outline"
                    size="icon"
                    aria-label="Decrease quantity"
                    disabled={item.quantity <= MIN_QUANTITY}
                    onClick={() => setQuantity(item.quantity - 1)}
                >
                    <Minus />
                </Button>

                <span
                    className="w-10 text-center tabular-nums"
                    aria-label="Quantity"
                >
                    {item.quantity}
                </span>

                <Button
                    variant="outline"
                    size="icon"
                    aria-label="Increase quantity"
                    disabled={item.quantity >= MAX_QUANTITY}
                    onClick={() => setQuantity(item.quantity + 1)}
                >
                    <Plus />
                </Button>
            </div>

            <p className="w-24 text-right font-medium tabular-nums">
                {formatPrice(item.line_total)}
            </p>

            <Form
                {...CartItemController.destroy.form(item.id)}
                options={{ preserveScroll: true }}
            >
                {({ processing }) => (
                    <Button
                        type="submit"
                        variant="ghost"
                        size="icon"
                        aria-label={`Remove ${item.product.title} from cart`}
                        disabled={processing}
                    >
                        <Trash2 />
                    </Button>
                )}
            </Form>
        </div>
    );
}
