import { Form, Link } from '@inertiajs/react';
import { useState } from 'react';
import CardBrandBadge from '@/components/card-brand-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { detectCardBrand, formatCardNumber } from '@/lib/card-brand';
import { index } from '@/routes/payment-methods';
import type { PaymentMethod } from '@/types';

const MONTHS = Array.from({ length: 12 }, (_, i) => i + 1);
const CURRENT_YEAR = new Date().getFullYear();
const YEARS = Array.from({ length: 21 }, (_, i) => CURRENT_YEAR + i);

type PaymentMethodFormProps = {
    /** Wayfinder form props, e.g. PaymentMethodController.store.form(). */
    action: Record<string, unknown>;
    paymentMethod?: PaymentMethod;
    submitLabel: string;
};

export default function PaymentMethodForm({
    action,
    paymentMethod,
    submitLabel,
}: PaymentMethodFormProps) {
    // The card number is the one controlled input in the app: every keystroke
    // reformats the value and re-detects the brand shown beside the field.
    const [cardNumber, setCardNumber] = useState('');
    const brand = detectCardBrand(cardNumber);

    const isEditing = paymentMethod !== undefined;

    return (
        <Form {...action} className="space-y-6">
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="number">Card number</Label>

                        {isEditing ? (
                            <div className="flex items-center justify-between rounded-md border border-input px-3 py-2 text-sm">
                                <span className="text-muted-foreground tabular-nums">
                                    {paymentMethod.masked_number}
                                </span>

                                <CardBrandBadge
                                    brand={paymentMethod.brand}
                                    label={paymentMethod.brand_label}
                                />
                            </div>
                        ) : (
                            <div className="relative">
                                <Input
                                    id="number"
                                    name="number"
                                    value={cardNumber}
                                    onChange={(event) =>
                                        setCardNumber(
                                            formatCardNumber(
                                                event.target.value,
                                            ),
                                        )
                                    }
                                    inputMode="numeric"
                                    autoComplete="cc-number"
                                    placeholder="4242 4242 4242 4242"
                                    className="pr-36 tabular-nums"
                                    required
                                />

                                <CardBrandBadge
                                    brand={brand}
                                    className="pointer-events-none absolute inset-y-0 right-3 my-auto h-fit"
                                />
                            </div>
                        )}

                        {isEditing ? (
                            <p className="text-xs text-muted-foreground">
                                Card numbers cannot be changed. Remove this card
                                and add a new one instead.
                            </p>
                        ) : (
                            <InputError message={errors.number} />
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="holder_name">Name on card</Label>

                        <Input
                            id="holder_name"
                            name="holder_name"
                            defaultValue={paymentMethod?.holder_name}
                            autoComplete="cc-name"
                            required
                        />

                        <InputError message={errors.holder_name} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="expiry_month">Expiry month</Label>

                            <Select
                                name="expiry_month"
                                defaultValue={
                                    paymentMethod
                                        ? String(paymentMethod.expiry_month)
                                        : undefined
                                }
                            >
                                <SelectTrigger
                                    id="expiry_month"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Month" />
                                </SelectTrigger>

                                <SelectContent>
                                    {MONTHS.map((month) => (
                                        <SelectItem
                                            key={month}
                                            value={String(month)}
                                        >
                                            {String(month).padStart(2, '0')}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <InputError message={errors.expiry_month} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="expiry_year">Expiry year</Label>

                            <Select
                                name="expiry_year"
                                defaultValue={
                                    paymentMethod
                                        ? String(paymentMethod.expiry_year)
                                        : undefined
                                }
                            >
                                <SelectTrigger
                                    id="expiry_year"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Year" />
                                </SelectTrigger>

                                <SelectContent>
                                    {YEARS.map((year) => (
                                        <SelectItem
                                            key={year}
                                            value={String(year)}
                                        >
                                            {year}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <InputError message={errors.expiry_year} />
                        </div>
                    </div>

                    <p className="text-xs text-muted-foreground">
                        The security code (CVV) is never requested or stored.
                    </p>

                    <div className="flex items-center gap-4">
                        <Button type="submit" disabled={processing}>
                            {submitLabel}
                        </Button>

                        <Button variant="ghost" asChild>
                            <Link href={index()}>Cancel</Link>
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
