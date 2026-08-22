import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import ProductCard from '@/components/product-card';
import { index } from '@/routes/products';
import type { Product } from '@/types';

export default function ProductIndex({ products }: { products: Product[] }) {
    return (
        <>
            <Head title="Products" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    title="Products"
                    description="Browse the full product catalog"
                />

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
