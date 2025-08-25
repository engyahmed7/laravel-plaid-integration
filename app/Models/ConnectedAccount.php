<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConnectedAccount extends Model
{
    protected $fillable = [
        'stripe_account_id',
        'email',
        'first_name',
        'last_name',
        'country',
        'onboarded',
        'payouts_enabled',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'onboarded' => 'boolean',
        'payouts_enabled' => 'boolean',
    ];

    public function transferRecords()
    {
        return $this->hasMany(TransferRecord::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
