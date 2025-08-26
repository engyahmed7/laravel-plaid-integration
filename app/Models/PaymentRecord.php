<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'stripe_payment_intent_id',
        'customer_id',
        'payment_method_id',
        'amount_cents',
        'currency',
        'status',
        'description',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'amount_cents' => 'integer',
    ];

    protected $appends = [
        'amount',
        'formatted_amount',
    ];

    public function getAmountAttribute()
    {
        return $this->amount_cents / 100;
    }

    public function getFormattedAmountAttribute()
    {
        return '$' . number_format($this->amount, 2);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'stripe_customer_id');
    }
}
