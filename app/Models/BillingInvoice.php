<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BillingInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'rental_id',
        'shop_owner_id',
        'invoice_number',
        'billing_period_start',
        'billing_period_end',
        'subtotal',
        'tax_amount',
        'total_amount',
        'status',
        'stripe_invoice_id',
        'stripe_payment_intent_id',
        'paid_at',
        'due_date',
        'notes',
        'billing_cycle',
    ];

    protected $casts = [
        'billing_period_start' => 'date',
        'billing_period_end' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'due_date' => 'date',
    ];

    // Invoice statuses
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';
    const STATUS_OVERDUE = 'overdue';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_FAILED = 'failed';

    // Billing cycles
    const BILLING_CYCLE_WEEKLY = 'weekly';
    const BILLING_CYCLE_MONTHLY = 'monthly';

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function shopOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shop_owner_id');
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_OVERDUE ||
            ($this->due_date && $this->due_date->isPast() && !$this->isPaid());
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function getFormattedTotalAttribute(): string
    {
        return '$' . number_format($this->total_amount, 2);
    }

    public function getFormattedSubtotalAttribute(): string
    {
        return '$' . number_format($this->subtotal, 2);
    }

    public function getFormattedTaxAttribute(): string
    {
        return '$' . number_format($this->tax_amount, 2);
    }

    public function getBillingPeriodDisplayAttribute(): string
    {
        return $this->billing_period_start->format('M j') . ' - ' . $this->billing_period_end->format('M j, Y');
    }

    public function getDaysOverdueAttribute(): int
    {
        if (!$this->isOverdue()) {
            return 0;
        }

        $referenceDate = $this->paid_at ?? now();
        return $this->due_date->diffInDays($referenceDate);
    }

    public function markAsPaid(): void
    {
        $this->update([
            'status' => self::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
        ]);
    }

    public function markAsOverdue(): void
    {
        if ($this->status !== self::STATUS_PAID) {
            $this->update([
                'status' => self::STATUS_OVERDUE,
            ]);
        }
    }
}
