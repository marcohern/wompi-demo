<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Carbon\CarbonImmutable;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $reference
 * @property int $amount_in_cents
 * @property string $currency
 * @property OrderStatus $status
 * @property string|null $wompi_transaction_id
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, OrderItem> $items
 */
#[Fillable(['reference', 'amount_in_cents', 'currency', 'status'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * Prefix marking a reference that is still waiting for the order's id.
     */
    public const PLACEHOLDER_PREFIX = 'PENDING-';

    /**
     * Generate the payment reference for an order.
     *
     * The id is embedded so every Wompi event, dashboard row and log line names
     * the order it belongs to without a database round trip. The random suffix
     * keeps the reference unguessable and lets a single order be re-attempted
     * under a fresh reference later.
     */
    public static function newReference(int $id): string
    {
        return 'WOMPI-'.$id.'-'.Str::upper(Str::random(8));
    }

    /**
     * Generate the throwaway reference an order is inserted with.
     *
     * The column is unique and not nullable, but the id only exists after the
     * insert, so a row is created with this and immediately given its real
     * reference inside the same transaction.
     */
    public static function placeholderReference(): string
    {
        return self::PLACEHOLDER_PREFIX.Str::uuid();
    }

    /**
     * Read the order id out of a payment reference.
     *
     * Diagnostics only -- the unique reference stays the source of truth when
     * resolving an order. Returns null for anything not in our format.
     */
    public static function idFromReference(string $reference): ?int
    {
        return preg_match('/^WOMPI-(\d+)-/', $reference, $matches) === 1
            ? (int) $matches[1]
            : null;
    }

    /**
     * Replace this order's placeholder reference with its real one.
     */
    public function assignReference(): void
    {
        $this->reference = self::newReference($this->id);
        $this->save();
    }

    /**
     * Get the user that placed the order.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the line items captured when the order was placed.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the order total in major currency units.
     */
    public function total(): float
    {
        return $this->amount_in_cents / 100;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'amount_in_cents' => 'integer',
            'paid_at' => 'datetime',
        ];
    }
}
