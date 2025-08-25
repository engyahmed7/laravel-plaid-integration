<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Rental extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_owner_id',
        'customer_id',
        'car_owner_id',
        'vehicle_id',
        'start_date',
        'end_date',
        'actual_return_date',
        'daily_rate',
        'total_amount',
        'rap_daily_rate',
        'rap_days',
        'rap_total',
        'status',
        'billing_cycle',
        'next_billing_date',
        'last_billed_date',
        'cancellation_reason',
        'cancelled_at',
        'extension_days',
        'early_return_days',
        'incident_charges',
        'commission_rate',
        'commission_amount',
        'payout_amount',
        'payout_status',
        'payout_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'actual_return_date' => 'date',
        'daily_rate' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'rap_daily_rate' => 'decimal:2',
        'rap_days' => 'integer',
        'rap_total' => 'decimal:2',
        'next_billing_date' => 'date',
        'last_billed_date' => 'date',
        'cancelled_at' => 'datetime',
        'extension_days' => 'integer',
        'early_return_days' => 'integer',
        'incident_charges' => 'decimal:2',
        'commission_rate' => 'decimal:4',
        'commission_amount' => 'decimal:2',
        'payout_amount' => 'decimal:2',
        'payout_date' => 'date',
    ];

    // Rental statuses
    const STATUS_PENDING = 'pending';
    const STATUS_ACTIVE = 'active';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_EXTENDED = 'extended';

    // Billing cycles
    const BILLING_CYCLE_WEEKLY = 'weekly';
    const BILLING_CYCLE_MONTHLY = 'monthly';

    // Payout statuses
    const PAYOUT_STATUS_PENDING = 'pending';
    const PAYOUT_STATUS_PROCESSING = 'processing';
    const PAYOUT_STATUS_COMPLETED = 'completed';
    const PAYOUT_STATUS_FAILED = 'failed';

    public function shopOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shop_owner_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function carOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'car_owner_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function billingInvoices(): HasMany
    {
        return $this->hasMany(BillingInvoice::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function securityDepositHold(): BelongsTo
    {
        return $this->belongsTo(SecurityDepositHold::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function getTotalDaysAttribute(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    public function getActualDaysAttribute(): int
    {
        if ($this->actual_return_date) {
            return $this->start_date->diffInDays($this->actual_return_date) + 1;
        }
        return $this->getTotalDaysAttribute();
    }

    public function getBaseRentalAmountAttribute(): float
    {
        return $this->daily_rate * $this->getActualDaysAttribute();
    }

    public function getTotalRentalAmountAttribute(): float
    {
        return $this->getBaseRentalAmountAttribute() + $this->rap_total + $this->incident_charges;
    }

    public function getPayoutAmountAttribute(): float
    {
        return $this->getBaseRentalAmountAttribute() - $this->commission_amount;
    }

    public function canBeBilled(): bool
    {
        return $this->isActive() &&
            $this->next_billing_date &&
            $this->next_billing_date->isPast();
    }

    public function shouldProcessPayout(): bool
    {
        return $this->isCompleted() &&
            $this->payout_status === self::PAYOUT_STATUS_PENDING;
    }
}
