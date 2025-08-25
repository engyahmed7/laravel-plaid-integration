<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payout extends Model
{
    use HasFactory;

    protected $fillable = [
        'rental_id',
        'car_owner_id',
        'bank_connection_id',
        'bank_account_id',
        'amount',
        'commission_amount',
        'net_amount',
        'status',
        'plaid_transfer_id',
        'stripe_payout_id',
        'scheduled_date',
        'processed_date',
        'failure_reason',
        'retry_count',
        'max_retries',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'scheduled_date' => 'date',
        'processed_date' => 'datetime',
        'retry_count' => 'integer',
        'max_retries' => 'integer',
    ];

    // Payout statuses
    const STATUS_PENDING = 'pending';
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    // Failure reasons
    const FAILURE_REASON_INSUFFICIENT_FUNDS = 'insufficient_funds';
    const FAILURE_REASON_ACCOUNT_CLOSED = 'account_closed';
    const FAILURE_REASON_INVALID_ACCOUNT = 'invalid_account';
    const FAILURE_REASON_BANK_ERROR = 'bank_error';
    const FAILURE_REASON_NETWORK_ERROR = 'network_error';
    const FAILURE_REASON_OTHER = 'other';

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function carOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'car_owner_id');
    }

    public function bankConnection(): BelongsTo
    {
        return $this->belongsTo(BankConnection::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function canBeProcessed(): bool
    {
        return $this->isPending() || $this->isScheduled();
    }

    public function canBeRetried(): bool
    {
        return $this->isFailed() && $this->retry_count < $this->max_retries;
    }

    public function getFormattedAmountAttribute(): string
    {
        return '$' . number_format($this->amount, 2);
    }

    public function getFormattedCommissionAttribute(): string
    {
        return '$' . number_format($this->commission_amount, 2);
    }

    public function getFormattedNetAmountAttribute(): string
    {
        return '$' . number_format($this->net_amount, 2);
    }

    public function getStatusDisplayAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_SCHEDULED => 'Scheduled',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_CANCELLED => 'Cancelled',
            default => 'Unknown',
        };
    }

    public function getFailureReasonDisplayAttribute(): string
    {
        return match ($this->failure_reason) {
            self::FAILURE_REASON_INSUFFICIENT_FUNDS => 'Insufficient Funds',
            self::FAILURE_REASON_ACCOUNT_CLOSED => 'Account Closed',
            self::FAILURE_REASON_INVALID_ACCOUNT => 'Invalid Account',
            self::FAILURE_REASON_BANK_ERROR => 'Bank Error',
            self::FAILURE_REASON_NETWORK_ERROR => 'Network Error',
            self::FAILURE_REASON_OTHER => 'Other Error',
            default => 'Unknown',
        };
    }

    public function markAsProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'processed_date' => now(),
        ]);
    }

    public function markAsFailed(string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'failure_reason' => $reason,
            'retry_count' => $this->retry_count + 1,
        ]);
    }

    public function scheduleForDate(string $date): void
    {
        $this->update([
            'status' => self::STATUS_SCHEDULED,
            'scheduled_date' => $date,
        ]);
    }

    public function cancel(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
        ]);
    }
}
