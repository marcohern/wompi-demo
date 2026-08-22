<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The largest quantity a single cart line may hold.
     */
    private const MAXIMUM_QUANTITY = 99;

    /**
     * Run the migrations.
     *
     * A user is only ever meant to own one cart, but nothing enforced it:
     * resolving the cart with firstOrCreate() is read-then-insert, so two
     * concurrent "add to cart" requests could leave a second cart behind.
     * Its items were then invisible on the cart page, which reads the
     * HasOne relation and therefore only ever sees the first cart.
     */
    public function up(): void
    {
        $this->mergeDuplicateCarts();

        Schema::table('carts', function (Blueprint $table) {
            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
        });
    }

    /**
     * Fold any extra carts into each user's oldest cart.
     *
     * This has to run before the unique index or the index cannot be built
     * on existing data.
     */
    private function mergeDuplicateCarts(): void
    {
        $duplicated = DB::table('carts')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('user_id');

        foreach ($duplicated as $userId) {
            $cartIds = DB::table('carts')
                ->where('user_id', $userId)
                ->orderBy('id')
                ->pluck('id');

            $keeperId = $cartIds->shift();

            foreach ($cartIds as $duplicateId) {
                $this->moveItems($duplicateId, $keeperId);
            }

            DB::table('carts')->whereIn('id', $cartIds)->delete();
        }
    }

    /**
     * Move one cart's items onto the keeper, folding matching products.
     *
     * Quantities are summed where the same product exists in both carts, so
     * the unique(cart_id, product_id) index is never violated.
     */
    private function moveItems(int $duplicateId, int $keeperId): void
    {
        $items = DB::table('cart_items')->where('cart_id', $duplicateId)->get();

        foreach ($items as $item) {
            $existing = DB::table('cart_items')
                ->where('cart_id', $keeperId)
                ->where('product_id', $item->product_id)
                ->first();

            if ($existing === null) {
                DB::table('cart_items')
                    ->where('id', $item->id)
                    ->update(['cart_id' => $keeperId]);

                continue;
            }

            DB::table('cart_items')
                ->where('id', $existing->id)
                ->update([
                    'quantity' => min($existing->quantity + $item->quantity, self::MAXIMUM_QUANTITY),
                ]);

            DB::table('cart_items')->where('id', $item->id)->delete();
        }
    }
};
