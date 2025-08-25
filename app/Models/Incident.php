<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Incident extends Model
{
    use HasFactory;

    protected $fillable = [
        'rental_id',
        'customer_id',
        'incident_type',
        'description',
        'location',
        'incident_date',
        'amount',
        'status',
        'admin_notes',
        'customer_notes',
        'stripe_charge_id',
        'stripe_refund_id',
        'processed_at',
        'processed_by',
    ];

    protected $casts = [
        'incident_date' => 'datetime',
        'amount' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    // Incident types
    const TYPE_TOWING = 'towing';
    const TYPE_TIRE_REPLACEMENT = 'tire_replacement';
    const TYPE_DAMAGE = 'damage';
    const TYPE_FUEL = 'fuel';
    const TYPE_LOCKOUT = 'lockout';
    const TYPE_JUMP_START = 'jump_start';
    const TYPE_OTHER = 'other';

    // Incident statuses
    const STATUS_REPORTED = 'reported';
    const STATUS_UNDER_REVIEW = 'under_review';
    const STATUS_APPROVED = 'approved';
    const STATUS_CHARGED = 'charged';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_DISMISSED = 'dismissed';

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function isReported(): bool
    {
        return $this->status === self::STATUS_REPORTED;
    }

    public function isUnderReview(): bool
    {
        return $this->status === self::STATUS_UNDER_REVIEW;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isCharged(): bool
    {
        return $this->status === self::STATUS_CHARGED;
    }

    public function isRefunded(): bool
    {
        return $this->status === self::STATUS_REFUNDED;
    }

    public function isDismissed(): bool
    {
        return $this->status === self::STATUS_DISMISSED;
    }

    public function canBeCharged(): bool
    {
        return $this->isApproved() && !$this->isCharged() && !$this->isRefunded();
    }

    public function canBeRefunded(): bool
    {
        return $this->isCharged() && !$this->isRefunded();
    }

    public function getFormattedAmountAttribute(): string
    {
        return '$' . number_format($this->amount, 2);
    }

    public function getIncidentTypeDisplayAttribute(): string
    {
        return match ($this->incident_type) {
            self::TYPE_TOWING => 'Towing Service',
            self::TYPE_TIRE_REPLACEMENT => 'Tire Replacement',
            self::TYPE_DAMAGE => 'Vehicle Damage',
            self::TYPE_FUEL => 'Fuel Service',
            self::TYPE_LOCKOUT => 'Lockout Service',
            self::TYPE_JUMP_START => 'Jump Start',
            self::TYPE_OTHER => 'Other Service',
            default => 'Unknown',
        };
    }

    public function getStatusDisplayAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_REPORTED => 'Reported',
            self::STATUS_UNDER_REVIEW => 'Under Review',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_CHARGED => 'Charged',
            self::STATUS_REFUNDED => 'Refunded',
            self::STATUS_DISMISSED => 'Dismissed',
            default => 'Unknown',
        };
    }

    public function isCoveredByRap(): bool
    {
        // Check if the incident type is covered by RAP
        $rapCoveredTypes = [
            self::TYPE_TOWING,
            self::TYPE_TIRE_REPLACEMENT,
            self::TYPE_LOCKOUT,
            self::TYPE_JUMP_START,
        ];

        return in_array($this->incident_type, $rapCoveredTypes);
    }

    public function shouldChargeCustomer(): bool
    {
        // Don't charge if covered by RAP or if dismissed
        if ($this->isCoveredByRap() || $this->isDismissed()) {
            return false;
        }

        // Charge for damage, fuel, or other non-RAP incidents
        return in_array($this->incident_type, [
            self::TYPE_DAMAGE,
            self::TYPE_FUEL,
            self::TYPE_OTHER,
        ]);
    }
}
