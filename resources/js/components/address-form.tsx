import { Form, Link } from '@inertiajs/react';
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
import { Textarea } from '@/components/ui/textarea';
import { index } from '@/routes/addresses';
import type { Address, CountryOption } from '@/types';

type AddressFormProps = {
    /** Wayfinder form props, e.g. AddressController.store.form(). */
    action: Record<string, unknown>;
    countries: CountryOption[];
    address?: Address;
    submitLabel: string;
};

export default function AddressForm({
    action,
    countries,
    address,
    submitLabel,
}: AddressFormProps) {
    return (
        <Form {...action} className="space-y-6">
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="country">Country</Label>

                        <Select name="country" defaultValue={address?.country}>
                            <SelectTrigger id="country" className="w-full">
                                <SelectValue placeholder="Select a country" />
                            </SelectTrigger>

                            <SelectContent>
                                {countries.map((country) => (
                                    <SelectItem
                                        key={country.value}
                                        value={country.value}
                                    >
                                        {country.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <InputError message={errors.country} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="political_region">
                            State / department / province
                        </Label>

                        <Input
                            id="political_region"
                            name="political_region"
                            defaultValue={address?.political_region ?? ''}
                            autoComplete="address-level1"
                            placeholder="Optional"
                        />

                        <InputError message={errors.political_region} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="city">City</Label>

                        <Input
                            id="city"
                            name="city"
                            defaultValue={address?.city}
                            required
                            autoComplete="address-level2"
                        />

                        <InputError message={errors.city} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="address1">Address line 1</Label>

                        <Input
                            id="address1"
                            name="address1"
                            defaultValue={address?.address1}
                            required
                            autoComplete="address-line1"
                        />

                        <InputError message={errors.address1} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="address2">Address line 2</Label>

                        <Input
                            id="address2"
                            name="address2"
                            defaultValue={address?.address2 ?? ''}
                            autoComplete="address-line2"
                            placeholder="Optional"
                        />

                        <InputError message={errors.address2} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="zip_code">Zip / postal code</Label>

                        <Input
                            id="zip_code"
                            name="zip_code"
                            defaultValue={address?.zip_code ?? ''}
                            autoComplete="postal-code"
                            placeholder="Optional"
                        />

                        <InputError message={errors.zip_code} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="notes">Delivery notes</Label>

                        <Textarea
                            id="notes"
                            name="notes"
                            defaultValue={address?.notes ?? ''}
                            placeholder="Optional, e.g. gate code or landmark"
                        />

                        <InputError message={errors.notes} />
                    </div>

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
