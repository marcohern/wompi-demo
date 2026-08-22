import { CreditCard } from 'lucide-react';
import type { CardBrand } from '@/lib/card-brand';
import { cardBrandLabel } from '@/lib/card-brand';
import { cn } from '@/lib/utils';

/**
 * Shows the detected card brand. Rendered live beside the card number input
 * as it is typed, and again against each saved card in the list.
 */
export default function CardBrandBadge({
    brand,
    label,
    className,
}: {
    brand: CardBrand | null;
    /** Overrides the derived label, e.g. the value sent by the server. */
    label?: string;
    className?: string;
}) {
    const text = label ?? (brand ? cardBrandLabel(brand) : 'Card');

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 text-xs font-medium',
                brand ? 'text-foreground' : 'text-muted-foreground',
                className,
            )}
            data-test="card-brand-badge"
            data-brand={brand ?? 'unknown'}
        >
            <CreditCard className="size-4" aria-hidden />
            <span>{text}</span>
        </span>
    );
}
