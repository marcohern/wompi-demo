/**
 * Card brand detection.
 *
 * This mirrors the CardBrand enum in app/Enums/CardBrand.php. The brand has to
 * be shown while the number is still being typed, so it cannot round trip to
 * the server. Both sides are covered by tests to stop the tables drifting.
 */
export type CardBrand =
    'visa' | 'mastercard' | 'amex' | 'discover' | 'diners' | 'jcb' | 'unionpay';

const BRAND_PATTERNS: ReadonlyArray<[CardBrand, RegExp]> = [
    ['visa', /^4/],
    ['mastercard', /^(5[1-5]|2[2-7])/],
    ['amex', /^3[47]/],
    ['discover', /^(6011|64[4-9]|65)/],
    ['diners', /^(30[0-5]|3[689])/],
    ['jcb', /^35(2[89]|[3-8])/],
    ['unionpay', /^62/],
];

const BRAND_LABELS: Record<CardBrand, string> = {
    visa: 'Visa',
    mastercard: 'Mastercard',
    amex: 'American Express',
    discover: 'Discover',
    diners: 'Diners Club',
    jcb: 'JCB',
    unionpay: 'UnionPay',
};

/** Strip everything that is not a digit. */
export function onlyDigits(value: string): string {
    return value.replace(/\D/g, '');
}

/** Detect the card brand from the digits typed so far, if recognisable. */
export function detectCardBrand(value: string): CardBrand | null {
    const digits = onlyDigits(value);

    if (digits.length === 0) {
        return null;
    }

    return (
        BRAND_PATTERNS.find(([, pattern]) => pattern.test(digits))?.[0] ?? null
    );
}

/** Get the display name for a brand. */
export function cardBrandLabel(brand: CardBrand): string {
    return BRAND_LABELS[brand];
}

/** The maximum number of digits a card of the given brand holds. */
export function maxCardLength(brand: CardBrand | null): number {
    if (brand === 'amex') {
        return 15;
    }

    if (brand === 'diners') {
        return 14;
    }

    return 19;
}

/**
 * Group digits the way the brand prints them: 4-6-5 for American Express,
 * 4-4-4-4 for everything else.
 */
export function formatCardNumber(value: string): string {
    const brand = detectCardBrand(value);
    const digits = onlyDigits(value).slice(0, maxCardLength(brand));

    const groups = brand === 'amex' ? [4, 6, 5] : [4, 4, 4, 4, 3];
    const parts: string[] = [];

    let offset = 0;

    for (const size of groups) {
        if (offset >= digits.length) {
            break;
        }

        parts.push(digits.slice(offset, offset + size));
        offset += size;
    }

    return parts.join(' ');
}
