<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    protected $fillable = [
        'bank_connection_id',
        'plaid_account_id',
        'stripe_payment_method_id',
        'stripe_external_account_id',
        'account_name',
        'account_type',
        'account_subtype',
        'mask',
        'routing_number',
        'balance_available',
        'balance_current',
        'balance_limit',
        'currency_code',
        'verification_status',
        'is_primary',
        'is_active',
    ];

    protected $casts = [
        'balance_available' => 'decimal:2',
        'balance_current' => 'decimal:2',
        'balance_limit' => 'decimal:2',
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function bankConnection(): BelongsTo
    {
        return $this->belongsTo(BankConnection::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    public function getFormattedBalanceAttribute(): string
    {
        return '$' . number_format($this->balance_current, 2);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->account_name . ' (...' . $this->mask . ')';
    }
}
