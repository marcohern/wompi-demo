<?php

use App\Enums\CardBrand;
use App\Rules\Luhn;

test('the card brand is detected from the issuer prefix', function (string $number, CardBrand $expected) {
    expect(CardBrand::detect($number))->toBe($expected);
})->with([
    'visa' => ['4242424242424242', CardBrand::Visa],
    'mastercard 5x' => ['5555555555554444', CardBrand::Mastercard],
    'mastercard 2x' => ['2223003122003222', CardBrand::Mastercard],
    'amex 37' => ['378282246310005', CardBrand::Amex],
    'amex 34' => ['341111111111111', CardBrand::Amex],
    'discover' => ['6011111111111117', CardBrand::Discover],
    'diners' => ['30569309025904', CardBrand::Diners],
    'jcb' => ['3530111333300000', CardBrand::Jcb],
    'unionpay' => ['6200000000000005', CardBrand::UnionPay],
]);

test('the brand is detected from formatted input', function () {
    expect(CardBrand::detect('4242 4242 4242 4242'))->toBe(CardBrand::Visa);
});

test('the brand is detected from a partial number as it is typed', function () {
    expect(CardBrand::detect('4'))->toBe(CardBrand::Visa)
        ->and(CardBrand::detect('37'))->toBe(CardBrand::Amex)
        ->and(CardBrand::detect('6011'))->toBe(CardBrand::Discover);
});

test('an unrecognised prefix has no brand', function (string $number) {
    expect(CardBrand::detect($number))->toBeNull();
})->with(['1234567890123456', '9999999999999999', '', 'abcd']);

test('every brand exposes a label', function (CardBrand $brand) {
    expect($brand->label())->not->toBeEmpty();
})->with(CardBrand::cases());

test('the luhn rule accepts valid card numbers', function (string $number) {
    expect((new Luhn)->passes($number))->toBeTrue();
})->with([
    '4242424242424242',
    '5555555555554444',
    '378282246310005',
    '6011111111111117',
    '30569309025904',
]);

test('the luhn rule rejects numbers that fail the checksum', function (string $number) {
    expect((new Luhn)->passes($number))->toBeFalse();
})->with([
    'mutated digit' => '4242424242424243',
    'transposed' => '4242424242424224',
    'too short' => '4242',
    'empty' => '',
]);
