import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import AddToCartButton from '@/components/add-to-cart-button';
import { Button } from '@/components/ui/button';
import { formatPrice } from '@/lib/utils';
import { index, show } from '@/routes/products';
import type { Product } from '@/types';

export default function ProductShow({ product }: { product: Product }) {
    return (
        <>
            <Head title={product.title} />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="grid gap-6 md:grid-cols-2">
                    <div className="aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 bg-muted dark:border-sidebar-border">
                        <img
                            src={product.image_url}
                            alt={product.title}
                            className="size-full object-cover"
                        />
                    </div>

                    <div className="flex flex-col gap-4">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {product.title}
                        </h1>

                        <p className="text-xl font-medium">
                            {formatPrice(product.price)}
                        </p>

                        <AddToCartButton product={product} />

                        <div>
                            <Button variant="outline" asChild>
                                <Link href={index()}>
                                    <ArrowLeft />
                                    Back to products
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

ProductShow.layout = ({ product }: { product: Product }) => ({
    breadcrumbs: [
        {
            title: 'Products',
            href: index(),
        },
        {
            title: product.title,
            href: show(product.id),
        },
    ],
});
