<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class WompiGateway
{
    /**
     * Build the Web Checkout URL the pay button redirects to.
     *
     * @see https://docs.wompi.co/docs/colombia/widget-checkout-web/
     */
    public function checkoutUrl(Order $order, string $redirectUrl): string
    {
        $query = http_build_query([
            'public-key' => $this->publicKey(),
            'currency' => $order->currency,
            'amount-in-cents' => $order->amount_in_cents,
            'reference' => $order->reference,
            'signature:integrity' => $this->integritySignature(
                $order->reference,
                $order->amount_in_cents,
                $order->currency,
            ),
            'redirect-url' => $this->redirectUrlFor($order, $redirectUrl),
        ]);

        return rtrim((string) config('wompi.checkout_url'), '?').'?'.$query;
    }

    /**
     * Resolve the return address and tag it with the order it belongs to.
     *
     * Wompi keeps whatever query string "redirect-url" already carries and
     * appends its own "id" and "env", so the order id survives the return trip
     * and the result page can find the order without depending on the
     * transaction lookup succeeding. Applied after the configured override is
     * resolved so a tunnel URL is tagged too.
     */
    private function redirectUrlFor(Order $order, string $fallback): string
    {
        $url = $this->redirectUrl($fallback);

        return $url.(str_contains($url, '?') ? '&' : '?').'order_id='.$order->id;
    }

    /**
     * Resolve the address Wompi returns the customer to.
     *
     * The configured override wins so the return trip can be pointed at a
     * tunnel without moving the host the app is browsed at.
     *
     * @throws InvalidArgumentException when the address would be rejected by Wompi
     */
    private function redirectUrl(string $fallback): string
    {
        $configured = config('wompi.redirect_url');
        $url = is_string($configured) && $configured !== '' ? $configured : $fallback;

        $this->assertReachableByWompi($url);

        return $url;
    }

    /**
     * Fail loudly on a redirect address Wompi's WAF will not accept.
     *
     * Wompi fronts Web Checkout with a WAF that blocks "redirect-url" values
     * naming a loopback or private-network host, and answers the whole checkout
     * request with an opaque CloudFront 403 rather than a useful error. Catching
     * it here turns that into something readable. Note that "wompi.localhost"
     * is accepted while a bare "localhost" is not, so the host is matched
     * exactly rather than by suffix.
     *
     * @throws InvalidArgumentException
     */
    private function assertReachableByWompi(string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            throw new InvalidArgumentException("Wompi redirect URL [{$url}] has no host.");
        }

        $isLoopbackName = strcasecmp($host, 'localhost') === 0;
        $isIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
        $isPublicIp = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;

        if ($isLoopbackName || ($isIp && ! $isPublicIp)) {
            throw new InvalidArgumentException(
                "Wompi rejects a redirect URL on [{$host}] with a CloudFront 403. ".
                'Browse the app at a non-loopback hostname such as http://wompi.localhost:8000 '.
                '(set APP_URL to match), or set WOMPI_REDIRECT_URL to a publicly reachable URL.'
            );
        }
    }

    /**
     * Build the integrity signature for a checkout.
     *
     * SHA256 of "<Reference><AmountInCents><Currency><IntegritySecret>".
     */
    public function integritySignature(string $reference, int $amountInCents, string $currency): string
    {
        return hash('sha256', $this->integrityPayload($reference, $amountInCents, $currency));
    }

    /**
     * Build the string that the integrity signature is computed from.
     *
     * Exposed separately so tests can assert the concatenation order against
     * the example published in the Wompi documentation.
     */
    public function integrityPayload(string $reference, int $amountInCents, string $currency): string
    {
        return $reference.$amountInCents.$currency.config('wompi.integrity_secret');
    }

    /**
     * Fetch a transaction from Wompi by its identifier.
     *
     * The public key is the documented credential for reading a transaction.
     *
     * @return array<string, mixed>|null
     */
    public function transaction(string $id): ?array
    {
        try {
            $response = Http::withToken($this->publicKey())
                ->acceptJson()
                ->get($this->baseUrl().'/transactions/'.$id);
        } catch (Throwable $e) {
            Log::warning('Wompi transaction lookup failed.', ['id' => $id, 'message' => $e->getMessage()]);

            return null;
        }

        if ($response->failed()) {
            Log::warning('Wompi transaction lookup returned an error.', [
                'id' => $id,
                'status' => $response->status(),
            ]);

            return null;
        }

        $data = $response->json('data');

        return is_array($data) ? $data : null;
    }

    /**
     * Verify the checksum on an incoming Wompi event.
     *
     * The checksum is the SHA256 of every value named in signature.properties
     * (read from the event's data), followed by the timestamp, followed by the
     * events secret.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyEventChecksum(array $payload, ?string $headerChecksum = null): bool
    {
        $concatenated = $this->eventChecksumPayload($payload);
        $checksum = $headerChecksum ?: Arr::get($payload, 'signature.checksum');

        if ($concatenated === null || ! is_string($checksum)) {
            return false;
        }

        return hash_equals(hash('sha256', $concatenated), strtolower($checksum));
    }

    /**
     * Build the string an event's checksum is computed from.
     *
     * Every value named in signature.properties, in order, then the
     * timestamp, then the events secret. Exposed separately so tests can
     * assert the concatenation against the example in the documentation.
     *
     * @param  array<string, mixed>  $payload
     */
    public function eventChecksumPayload(array $payload): ?string
    {
        $properties = Arr::get($payload, 'signature.properties');
        $timestamp = Arr::get($payload, 'timestamp');

        if (! is_array($properties) || $properties === [] || $timestamp === null) {
            return null;
        }

        $concatenated = '';

        foreach ($properties as $property) {
            $value = Arr::get($payload, 'data.'.$property);

            if ($value === null) {
                return null;
            }

            $concatenated .= is_bool($value) ? var_export($value, true) : (string) $value;
        }

        return $concatenated.$timestamp.config('wompi.events_secret');
    }

    /**
     * Get the configured API base URL.
     */
    private function baseUrl(): string
    {
        return rtrim((string) config('wompi.base_url'), '/');
    }

    /**
     * Get the configured public key.
     */
    private function publicKey(): string
    {
        return (string) config('wompi.public_key');
    }
}
