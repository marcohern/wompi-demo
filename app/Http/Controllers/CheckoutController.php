<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Services\WompiGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function __construct(private readonly WompiGateway $wompi) {}

    /**
     * Show a read only summary of the cart with the pay button.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        $items = $this->cartItems($request);

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
    public function pay(Request $request): RedirectResponse
    {
        $items = $this->cartItems($request);

        if ($items->isEmpty()) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => __('Your cart is empty.')]);

            return to_route('products.index');
        }

        $order = DB::transaction(function () use ($request, $items): Order {
            $order = $request->user()->orders()->create([
                'reference' => Order::newReference(),
                'amount_in_cents' => (int) round($this->total($items) * 100),
                'currency' => (string) config('wompi.currency'),
                'status' => OrderStatus::Pending,
            ]);

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

        return redirect()->away(
            $this->wompi->checkoutUrl($order, route('checkout.result')),
        );
    }

    /**
     * Handle the return trip from Wompi and settle the order.
     */
    public function result(Request $request): Response|RedirectResponse
    {
        $transactionId = $request->query('id');
        $order = null;

        if (is_string($transactionId) && $transactionId !== '') {
            $transaction = $this->wompi->transaction($transactionId);

            if ($transaction !== null) {
                $order = $request->user()
                    ->orders()
                    ->where('reference', $transaction['reference'] ?? '')
                    ->first();

                if ($order instanceof Order) {
                    $this->settle($order, (string) ($transaction['status'] ?? ''), $transactionId);
                }
            }
        }

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
     * Get the authenticated user's cart lines with their products loaded.
     *
     * @return Collection<int, CartItem>
     */
    private function cartItems(Request $request): Collection
    {
        $cart = $request->user()->cart;

        if (! $cart instanceof Cart) {
            return collect();
        }

        return $cart->items()->with('product')->oldest('id')->get();
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
