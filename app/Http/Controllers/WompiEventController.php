<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\WompiGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WompiEventController extends Controller
{
    public function __construct(private readonly WompiGateway $wompi) {}

    /**
     * Receive a Wompi webhook event.
     *
     * This settles an order even when the customer never returns from the
     * checkout page. Wompi retries non-200 responses three times over 24
     * hours, so a verified event always answers 200 even when it is a
     * duplicate or refers to an order this application does not know about.
     *
     * @see https://docs.wompi.co/docs/colombia/eventos/
     */
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();

        if (! $this->wompi->verifyEventChecksum($payload, $request->header('X-Event-Checksum'))) {
            Log::warning('Rejected a Wompi event with an invalid checksum.');

            return response()->json(['message' => 'Invalid signature.'], 403);
        }

        $reference = Arr::get($payload, 'data.transaction.reference');
        $status = Arr::get($payload, 'data.transaction.status');

        if (! is_string($reference) || ! is_string($status)) {
            return response()->json(['received' => true]);
        }

        $order = Order::query()->where('reference', $reference)->first();

        if (! $order instanceof Order) {
            return response()->json(['received' => true]);
        }

        $this->settle($order, $status, Arr::get($payload, 'data.transaction.id'));

        return response()->json(['received' => true]);
    }

    /**
     * Apply the event's status to the order, emptying the cart once paid.
     */
    private function settle(Order $order, string $wompiStatus, mixed $transactionId): void
    {
        $status = OrderStatus::fromWompi($wompiStatus);

        DB::transaction(function () use ($order, $status, $transactionId): void {
            $order->status = $status;

            if (is_string($transactionId) || is_int($transactionId)) {
                $order->wompi_transaction_id = (string) $transactionId;
            }

            // Guarded so a replayed event never moves the payment timestamp.
            if ($status->isPaid() && $order->paid_at === null) {
                $order->paid_at = now();
            }

            $order->save();

            if ($status->isPaid()) {
                $order->user->cart?->items()->delete();
            }
        });
    }
}
