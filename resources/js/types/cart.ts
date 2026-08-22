import type { Product } from './product';

export type CartItem = {
    id: number;
    quantity: number;
    line_total: number;
    product: Product;
};
