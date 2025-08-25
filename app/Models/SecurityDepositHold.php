<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SecurityDepositHold extends Model
{
    use HasFactory;

    protected $fillable = [
        'rental_id',
        'customer_id',
        'amount',
        'stripe_payment_intent_id',
        'stripe_charge_id',
        'status',
        'hold_date',
        'release_date',
        'released_amount',
        'withheld_amount',
        'release_reason',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'released_amount' => 'decimal:2',
        'withheld_amount' => 'decimal:2',
        'hold_date' => 'datetime',
        'release_date' => 'datetime',
    ];

    // Hold statuses
    const STATUS_PENDING = 'pending';
    const STATUS_ACTIVE = 'active';
    const STATUS_PARTIALLY_RELEASED = 'partially_released';
    const STATUS_FULLY_RELEASED = 'fully_released';
    const STATUS_FAILED = 'failed';

    // Release reasons
    const RELEASE_REASON_RENTAL_COMPLETED = 'rental_completed';
    const RELEASE_REASON_INCIDENT_CHARGE = 'incident_charge';
    const RELEASE_REASON_PARTIAL_INCIDENT = 'partial_incident';
    const RELEASE_REASON_ADMIN_OVERRIDE = 'admin_override';

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPartiallyReleased(): bool
    {
        return $this->status === self::STATUS_PARTIALLY_RELEASED;
    }

    public function isFullyReleased(): bool
    {
        return $this->status === self::STATUS_FULLY_RELEASED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function canBeReleased(): bool
    {
        return $this->isActive() || $this->isPartiallyReleased();
    }

    public function getRemainingAmountAttribute(): float
    {
        return $this->amount - $this->released_amount;
    }

    public function getFormattedAmountAttribute(): string
    {
        return '$' . number_format($this->amount, 2);
    }

    public function getFormattedReleasedAmountAttribute(): string
    {
        return '$' . number_format($this->released_amount, 2);
    }

    public function getFormattedWithheldAmountAttribute(): string
    {
        return '$' . number_format($this->withheld_amount, 2);
    }

    public function getFormattedRemainingAmountAttribute(): string
    {
        return '$' . number_format($this->getRemainingAmountAttribute(), 2);
    }

    public function getReleaseReasonDisplayAttribute(): string
    {
        return match ($this->release_reason) {
            self::RELEASE_REASON_RENTAL_COMPLETED => 'Rental Completed',
            self::RELEASE_REASON_INCIDENT_CHARGE => 'Incident Charge Applied',
            self::RELEASE_REASON_PARTIAL_INCIDENT => 'Partial Incident Charge',
            self::RELEASE_REASON_ADMIN_OVERRIDE => 'Admin Override',
            default => 'Unknown',
        };
    }

    public function getStatusDisplayAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_PARTIALLY_RELEASED => 'Partially Released',
            self::STATUS_FULLY_RELEASED => 'Fully Released',
            self::STATUS_FAILED => 'Failed',
            default => 'Unknown',
        };
    }

    public function markAsActive(): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'hold_date' => now(),
        ]);
    }

    public function releaseAmount(float $amount, string $reason = null): void
    {
        $newReleasedAmount = $this->released_amount + $amount;
        $newWithheldAmount = $this->amount - $newReleasedAmount;

        $status = $newReleasedAmount >= $this->amount
            ? self::STATUS_FULLY_RELEASED
            : self::STATUS_PARTIALLY_RELEASED;

        $this->update([
            'released_amount' => $newReleasedAmount,
            'withheld_amount' => $newWithheldAmount,
            'status' => $status,
            'release_date' => now(),
            'release_reason' => $reason,
        ]);
    }

    public function withholdAmount(float $amount): void
    {
        $this->update([
            'withheld_amount' => $amount,
            'released_amount' => $this->amount - $amount,
            'status' => self::STATUS_PARTIALLY_RELEASED,
            'release_date' => now(),
        ]);
    }
}
