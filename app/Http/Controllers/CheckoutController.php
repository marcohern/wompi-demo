<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\CartItem;
use App\Models\Order;
use App\Services\WompiGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CheckoutController extends Controller
{
    public function __construct(private readonly WompiGateway $wompi) {}

    /**
     * Show a read only summary of the cart with the pay button.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        $items = $request->user()->cartItems();

        if ($items->isEmpty()) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => __('Your cart is empty.')]);

            return to_route('products.index');
        }

        return Inertia::render('checkout/index', [
            'items' => $items
                ->map(fn (CartItem $item) => [
                    'id' => $item->id,
                    'title' => $item->product->title,
                    'image_url' => $item->product->image_url,
                    'unit_price' => (float) $item->product->price,
                    'quantity' => $item->quantity,
                    'line_total' => $item->lineTotal(),
                ])
                ->values()
                ->all(),
            'total' => $this->total($items),
            'currency' => (string) config('wompi.currency'),
        ]);
    }

    /**
     * Create the order and hand the customer off to Wompi.
     *
     * The amount and the integrity signature are both derived here rather than
     * submitted by the browser, so neither can be tampered with.
     */
    public function pay(Request $request): SymfonyResponse
    {
        $items = $request->user()->cartItems();

        if ($items->isEmpty()) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => __('Your cart is empty.')]);

            return to_route('products.index');
        }

        $order = DB::transaction(function () use ($request, $items): Order {
            $order = $request->user()->orders()->create([
                'reference' => Order::placeholderReference(),
                'amount_in_cents' => (int) round($this->total($items) * 100),
                'currency' => (string) config('wompi.currency'),
                'status' => OrderStatus::Pending,
            ]);

            // The reference carries the order id, which only exists once the
            // row is inserted. Still inside the transaction, so the placeholder
            // is never visible outside it.
            $order->assignReference();

            // Snapshot each line so the order total never moves when a
            // product is re-priced or removed from the catalog.
            foreach ($items as $item) {
                $order->items()->create([
                    'product_id' => $item->product_id,
                    'title' => $item->product->title,
                    'unit_price' => $item->product->price,
                    'quantity' => $item->quantity,
                ]);
            }

            return $order;
        });

        // Inertia::location, not redirect()->away: the pay button submits over
        // XHR, and an XHR visit cannot follow a 302 to another host. This
        // returns a 409 with X-Inertia-Location so the client performs a full
        // window.location visit to Wompi.
        return Inertia::location(
            $this->wompi->checkoutUrl($order, route('checkout.result')),
        );
    }

    /**
     * Handle the return trip from Wompi and settle the order.
     */
    public function result(Request $request): Response|RedirectResponse
    {
        $order = $this->orderFromRequest($request);
        $transactionId = $request->query('id');

        if (is_string($transactionId) && $transactionId !== '') {
            $transaction = $this->wompi->transaction($transactionId);

            if ($transaction !== null) {
                $reference = (string) ($transaction['reference'] ?? '');

                // Without an order_id the reference is the only way back to the
                // order. Scoped to the user either way, so one customer can
                // never settle another's order.
                $order ??= $request->user()
                    ->orders()
                    ->where('reference', $reference)
                    ->first();

                // A mismatch means order_id and the transaction disagree; trust
                // neither rather than settling the wrong order.
                if ($order instanceof Order && $order->reference === $reference) {
                    $this->settle($order, (string) ($transaction['status'] ?? ''), $transactionId);
                }
            }
        }

        // An order resolved from order_id is still worth rendering when the
        // lookup failed -- the webhook may have already settled it.
        if (! $order instanceof Order) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('We could not find that payment.')]);

            return to_route('products.index');
        }

        return Inertia::render('checkout/result', [
            'order' => [
                'reference' => $order->reference,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'is_paid' => $order->status->isPaid(),
                'total' => $order->total(),
                'transaction_id' => $order->wompi_transaction_id,
            ],
        ]);
    }

    /**
     * Resolve the order named by the order_id Wompi carried back on the
     * redirect URL, scoped to the authenticated customer.
     */
    private function orderFromRequest(Request $request): ?Order
    {
        $orderId = $request->query('order_id');

        if (! is_string($orderId) || ! ctype_digit($orderId)) {
            return null;
        }

        return $request->user()->orders()->find((int) $orderId);
    }

    /**
     * Apply a Wompi status to an order, emptying the cart once it is paid.
     */
    private function settle(Order $order, string $wompiStatus, string $transactionId): void
    {
        $status = OrderStatus::fromWompi($wompiStatus);

        DB::transaction(function () use ($order, $status, $transactionId): void {
            $order->status = $status;
            $order->wompi_transaction_id = $transactionId;

            if ($status->isPaid() && $order->paid_at === null) {
                $order->paid_at = now();
            }

            $order->save();

            if ($status->isPaid()) {
                $order->user->cart?->items()->delete();
            }
        });
    }

    /**
     * Sum the cart lines using current product prices.
     *
     * @param  Collection<int, CartItem>  $items
     */
    private function total(Collection $items): float
    {
        return (float) $items->sum(fn (CartItem $item) => $item->lineTotal());
    }
}
