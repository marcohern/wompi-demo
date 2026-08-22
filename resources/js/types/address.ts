export type Address = {
    id: number;
    country: string;
    country_name: string;
    political_region: string | null;
    city: string;
    address1: string;
    address2: string | null;
    zip_code: string | null;
    notes: string | null;
    is_default: boolean;
};

export type CountryOption = {
    value: string;
    label: string;
};
