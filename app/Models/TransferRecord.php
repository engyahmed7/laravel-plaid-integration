<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransferRecord extends Model
{
    protected $fillable = [
        'stripe_transfer_id',
        'connected_account_id',
        'amount_cents',
        'currency',
        'status',
        'description',
        'metadata',
        'transferred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'transferred_at' => 'datetime',
    ];

    public function connectedAccount()
    {
        return $this->belongsTo(ConnectedAccount::class);
    }

    public function getAmountAttribute(): float
    {
        return $this->amount_cents / 100;
    }
}
