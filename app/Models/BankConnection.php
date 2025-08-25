<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankConnection extends Model
{
    protected $fillable = [
        'user_id',
        'plaid_access_token',
        'plaid_item_id',
        'plaid_public_token',
        'stripe_customer_id',
        'institution_name',
        'institution_id',
        'status',
        'error_code',
        'error_message',
        'accounts_count',
        'last_sync_at',
    ];

    protected $casts = [
        'last_sync_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasError(): bool
    {
        return !empty($this->error_code);
    }
}
