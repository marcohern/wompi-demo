<?php

namespace App\Enums;

enum CardBrand: string
{
    case Visa = 'visa';
    case Mastercard = 'mastercard';
    case Amex = 'amex';
    case Discover = 'discover';
    case Diners = 'diners';
    case Jcb = 'jcb';
    case UnionPay = 'unionpay';

    /**
     * Detect the card brand from an issuer identification number.
     *
     * The front end mirrors this table in resources/js/lib/card-brand.ts so
     * the brand can be shown while the number is still being typed.
     */
    public static function detect(string $number): ?self
    {
        $digits = preg_replace('/\D/', '', $number) ?? '';

        return match (true) {
            (bool) preg_match('/^4/', $digits) => self::Visa,
            (bool) preg_match('/^(5[1-5]|2[2-7])/', $digits) => self::Mastercard,
            (bool) preg_match('/^3[47]/', $digits) => self::Amex,
            (bool) preg_match('/^(6011|64[4-9]|65)/', $digits) => self::Discover,
            (bool) preg_match('/^(30[0-5]|3[689])/', $digits) => self::Diners,
            (bool) preg_match('/^35(2[89]|[3-8])/', $digits) => self::Jcb,
            (bool) preg_match('/^62/', $digits) => self::UnionPay,
            default => null,
        };
    }

    /**
     * Get the human readable brand name.
     */
    public function label(): string
    {
        return match ($this) {
            self::Visa => 'Visa',
            self::Mastercard => 'Mastercard',
            self::Amex => 'American Express',
            self::Discover => 'Discover',
            self::Diners => 'Diners Club',
            self::Jcb => 'JCB',
            self::UnionPay => 'UnionPay',
        };
    }

    /**
     * Get the number of digits a card of this brand contains.
     *
     * @return array<int, int>
     */
    public function lengths(): array
    {
        return match ($this) {
            self::Amex => [15],
            self::Diners => [14],
            default => [16, 17, 18, 19],
        };
    }
}
