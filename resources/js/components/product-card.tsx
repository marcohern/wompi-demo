import { Link } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/card';
import { formatPrice } from '@/lib/utils';
import { show } from '@/routes/products';
import type { Product } from '@/types';

export default function ProductCard({ product }: { product: Product }) {
    return (
        <Card className="overflow-hidden py-0">
            <Link
                href={show(product.id)}
                className="block aspect-video overflow-hidden bg-muted"
                aria-label={product.title}
                prefetch
            >
                <img
                    src={product.image_url}
                    alt={product.title}
                    loading="lazy"
                    className="size-full object-cover transition-transform hover:scale-105"
                />
            </Link>

            <CardContent className="flex flex-col gap-1 p-4">
                <Link
                    href={show(product.id)}
                    className="font-medium hover:underline"
                    prefetch
                >
                    {product.title}
                </Link>

                <span className="text-sm text-muted-foreground">
                    {formatPrice(product.price)}
                </span>
            </CardContent>
        </Card>
    );
}
