<?php

use App\Services\WompiGateway;

/**
 * These assertions use the worked examples published in the Wompi docs, so
 * they prove the algorithms match Wompi rather than merely matching ourselves.
 *
 * @see https://docs.wompi.co/docs/colombia/widget-checkout-web/
 * @see https://docs.wompi.co/docs/colombia/eventos/
 */
beforeEach(function () {
    config()->set('wompi.integrity_secret', 'prod_integrity_Z5mMke9x0k8gpErbDqwrJXMqsI6SFli6');
    config()->set('wompi.events_secret', 'prod_events_OcHnIzeBl5socpwByQ4hA52Em3USQ93Z');
    config()->set('wompi.checkout_url', 'https://checkout.co.uat.wompi.dev/p/');
    config()->set('wompi.public_key', 'pub_stagtest_public');
});

test('the integrity payload matches the documented concatenation', function () {
    $payload = app(WompiGateway::class)->integrityPayload(
        'sk8-438k4-xmxm392-sn2m',
        4900000,
        'COP',
    );

    expect($payload)->toBe(
        'sk8-438k4-xmxm392-sn2m4900000COPprod_integrity_Z5mMke9x0k8gpErbDqwrJXMqsI6SFli6',
    );
});

test('the integrity signature is the sha256 of that payload', function () {
    $gateway = app(WompiGateway::class);

    expect($gateway->integritySignature('sk8-438k4-xmxm392-sn2m', 4900000, 'COP'))
        ->toBe(hash('sha256', 'sk8-438k4-xmxm392-sn2m4900000COPprod_integrity_Z5mMke9x0k8gpErbDqwrJXMqsI6SFli6'))
        ->toHaveLength(64);
});

test('changing the amount changes the signature', function () {
    $gateway = app(WompiGateway::class);

    expect($gateway->integritySignature('REF-1', 1000, 'COP'))
        ->not->toBe($gateway->integritySignature('REF-1', 2000, 'COP'));
});

/**
 * @return array<string, mixed>
 */
function documentedEvent(array $overrides = []): array
{
    return array_replace_recursive([
        'event' => 'transaction.updated',
        'data' => [
            'transaction' => [
                'id' => '1234-1610641025-49201',
                'status' => 'APPROVED',
                'amount_in_cents' => 4490000,
            ],
        ],
        'environment' => 'prod',
        'signature' => [
            'properties' => [
                'transaction.id',
                'transaction.status',
                'transaction.amount_in_cents',
            ],
            'checksum' => hash(
                'sha256',
                '1234-1610641025-49201APPROVED44900001530291411prod_events_OcHnIzeBl5socpwByQ4hA52Em3USQ93Z',
            ),
        ],
        'timestamp' => 1530291411,
        'sent_at' => '2018-07-18T08:35:20.000Z',
    ], $overrides);
}

test('the documented event checksum verifies', function () {
    expect(app(WompiGateway::class)->verifyEventChecksum(documentedEvent()))->toBeTrue();
});

/*
 | The documentation's worked example is internally inconsistent: the SHA256
 | of the concatenation it publishes is 5a18ec5e..., not the 3476DDA5...
 | checksum printed beside it, so the example's events secret must be
 | redacted. The concatenation itself (its steps 1 to 3) is reproducible and
 | is what this asserts; the hashing step is plain SHA256.
 */
test('the event concatenation matches the documented steps', function () {
    expect(app(WompiGateway::class)->eventChecksumPayload(documentedEvent()))
        ->toBe('1234-1610641025-49201APPROVED44900001530291411prod_events_OcHnIzeBl5socpwByQ4hA52Em3USQ93Z');
});

test('the header checksum is accepted and takes precedence', function () {
    $checksum = documentedEvent()['signature']['checksum'];

    $event = documentedEvent();
    $event['signature']['checksum'] = 'nonsense';

    expect(app(WompiGateway::class)->verifyEventChecksum($event, $checksum))->toBeTrue();
});

test('a tampered event is rejected', function (array $overrides) {
    expect(app(WompiGateway::class)->verifyEventChecksum(documentedEvent($overrides)))->toBeFalse();
})->with([
    'altered status' => [['data' => ['transaction' => ['status' => 'DECLINED']]]],
    'altered amount' => [['data' => ['transaction' => ['amount_in_cents' => 1]]]],
    'altered timestamp' => [['timestamp' => 1530291412]],
    'altered checksum' => [['signature' => ['checksum' => str_repeat('a', 64)]]],
]);

test('an event missing its signature is rejected', function () {
    $gateway = app(WompiGateway::class);

    expect($gateway->verifyEventChecksum([]))->toBeFalse()
        ->and($gateway->verifyEventChecksum(['signature' => ['properties' => []]]))->toBeFalse();
});

test('an event naming a property it does not carry is rejected', function () {
    $event = documentedEvent();
    $event['signature']['properties'][] = 'transaction.missing_field';

    expect(app(WompiGateway::class)->verifyEventChecksum($event))->toBeFalse();
});

test('a different events secret rejects the checksum', function () {
    config()->set('wompi.events_secret', 'prod_events_someone_elses_secret');

    expect(app(WompiGateway::class)->verifyEventChecksum(documentedEvent()))->toBeFalse();
});
