import type { CardBrand } from '@/lib/card-brand';

export type PaymentMethod = {
    id: number;
    brand: CardBrand;
    brand_label: string;
    last_four: string;
    masked_number: string;
    holder_name: string;
    expiry_month: number;
    expiry_year: number;
    is_default: boolean;
    is_expired: boolean;
};
