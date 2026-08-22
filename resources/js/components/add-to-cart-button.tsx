import { Form } from '@inertiajs/react';
import { ShoppingCart } from 'lucide-react';
import CartItemController from '@/actions/App/Http/Controllers/CartItemController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import type { Product } from '@/types';

export default function AddToCartButton({ product }: { product: Product }) {
    return (
        <Form
            {...CartItemController.store.form()}
            options={{ preserveScroll: true }}
            resetOnSuccess
        >
            {({ processing, errors }) => (
                <>
                    <input type="hidden" name="product_id" value={product.id} />

                    <Button type="submit" disabled={processing}>
                        <ShoppingCart />
                        Add to cart
                    </Button>

                    <InputError
                        message={errors.product_id ?? errors.quantity}
                    />
                </>
            )}
        </Form>
    );
}
