<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
            'redirect-url' => $redirectUrl,
        ]);

        return rtrim((string) config('wompi.checkout_url'), '?').'?'.$query;
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
