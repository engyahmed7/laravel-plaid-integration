<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'bank_connection_id',
        'bank_account_id',
        'plaid_transaction_id',
        'stripe_transaction_id',
        'amount',
        'currency_code',
        'description',
        'merchant_name',
        'category',
        'subcategory',
        'transaction_type',
        'transaction_date',
        'authorized_date',
        'account_owner',
        'location_address',
        'location_city',
        'location_region',
        'location_postal_code',
        'location_country',
        'payment_channel',
        'pending',
        'synced_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
        'authorized_date' => 'date',
        'pending' => 'boolean',
        'synced_at' => 'datetime',
    ];

    public function bankConnection(): BelongsTo
    {
        return $this->belongsTo(BankConnection::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function getFormattedAmountAttribute(): string
    {
        $prefix = $this->amount >= 0 ? '+' : '-';
        return $prefix . '$' . number_format(abs($this->amount), 2);
    }

    public function getCategoryDisplayAttribute(): string
    {
        return $this->subcategory ?: $this->category;
    }

    public function getLocationDisplayAttribute(): string
    {
        $parts = array_filter([
            $this->location_city,
            $this->location_region,
            $this->location_country
        ]);

        return implode(', ', $parts);
    }

    public function isDebit(): bool
    {
        return $this->amount < 0;
    }

    public function isCredit(): bool
    {
        return $this->amount > 0;
    }
}
