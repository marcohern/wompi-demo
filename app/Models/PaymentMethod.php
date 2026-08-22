<?php

namespace App\Models;

use App\Enums\CardBrand;
use Database\Factories\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property CardBrand $brand
 * @property string $last_four
 * @property string $number
 * @property string $holder_name
 * @property int $expiry_month
 * @property int $expiry_year
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['holder_name', 'expiry_month', 'expiry_year'])]
#[Hidden(['number'])]
class PaymentMethod extends Model
{
    /** @use HasFactory<PaymentMethodFactory> */
    use HasFactory;

    /**
     * Get the user that owns the payment method.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the masked representation of the card number.
     */
    public function maskedNumber(): string
    {
        return '•••• •••• •••• '.$this->last_four;
    }

    /**
     * Determine if the card's expiry date has already passed.
     */
    public function isExpired(): bool
    {
        return Carbon::createFromDate($this->expiry_year, $this->expiry_month, 1)
            ->endOfMonth()
            ->isPast();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'brand' => CardBrand::class,
            'number' => 'encrypted',
            'expiry_month' => 'integer',
            'expiry_year' => 'integer',
            'is_default' => 'boolean',
        ];
    }
}
