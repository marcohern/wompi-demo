<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';
    case Voided = 'voided';
    case Error = 'error';

    /**
     * Map a Wompi transaction status onto a local order status.
     *
     * Wompi reports PENDING, APPROVED, DECLINED, VOIDED and ERROR. Anything
     * unrecognised is treated as an error rather than silently approved.
     */
    public static function fromWompi(string $status): self
    {
        return match (strtoupper($status)) {
            'PENDING' => self::Pending,
            'APPROVED' => self::Approved,
            'DECLINED' => self::Declined,
            'VOIDED' => self::Voided,
            default => self::Error,
        };
    }

    /**
     * Get the human readable status name.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Declined => 'Declined',
            self::Voided => 'Voided',
            self::Error => 'Error',
        };
    }

    /**
     * Determine if the payment completed successfully.
     */
    public function isPaid(): bool
    {
        return $this === self::Approved;
    }
}
