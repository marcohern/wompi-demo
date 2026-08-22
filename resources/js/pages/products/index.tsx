import { Head, Link } from '@inertiajs/react';
import { CreditCard } from 'lucide-react';
import Heading from '@/components/heading';
import ProductCard from '@/components/product-card';
import { Button } from '@/components/ui/button';
import { show as checkout } from '@/routes/checkout';
import { index } from '@/routes/products';
import type { Product } from '@/types';

export default function ProductIndex({ products }: { products: Product[] }) {
    return (
        <>
            <Head title="Products" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Products"
                        description="Browse the full product catalog"
                    />

                    <Button variant="outline" asChild>
                        <Link href={checkout()}>
                            <CreditCard />
                            Checkout
                        </Link>
                    </Button>
                </div>

                {products.length === 0 ? (
                    <div className="rounded-xl border border-sidebar-border/70 p-8 text-center text-sm text-muted-foreground dark:border-sidebar-border">
                        No products yet.
                    </div>
                ) : (
                    <div className="grid auto-rows-min gap-4 sm:grid-cols-2 md:grid-cols-3">
                        {products.map((product) => (
                            <ProductCard key={product.id} product={product} />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

ProductIndex.layout = {
    breadcrumbs: [
        {
            title: 'Products',
            href: index(),
        },
    ],
};
