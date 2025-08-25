<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'billing_invoice_id',
        'description',
        'quantity',
        'unit_price',
        'total_price',
        'item_type',
        'rental_days',
        'daily_rate',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'rental_days' => 'integer',
        'daily_rate' => 'decimal:2',
    ];

    // Item types
    const ITEM_TYPE_RENTAL = 'rental';
    const ITEM_TYPE_RAP = 'rap';
    const ITEM_TYPE_EXTENSION = 'extension';
    const ITEM_TYPE_EARLY_RETURN = 'early_return';
    const ITEM_TYPE_CANCELLATION = 'cancellation';
    const ITEM_TYPE_TAX = 'tax';
    const ITEM_TYPE_FEE = 'fee';

    public function billingInvoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class);
    }

    public function getFormattedUnitPriceAttribute(): string
    {
        return '$' . number_format($this->unit_price, 2);
    }

    public function getFormattedTotalPriceAttribute(): string
    {
        return '$' . number_format($this->total_price, 2);
    }

    public function getFormattedQuantityAttribute(): string
    {
        if ($this->item_type === self::ITEM_TYPE_RENTAL) {
            return $this->rental_days . ' day' . ($this->rental_days > 1 ? 's' : '');
        }

        return $this->quantity;
    }

    public function isRentalItem(): bool
    {
        return $this->item_type === self::ITEM_TYPE_RENTAL;
    }

    public function isRapItem(): bool
    {
        return $this->item_type === self::ITEM_TYPE_RAP;
    }

    public function isExtensionItem(): bool
    {
        return $this->item_type === self::ITEM_TYPE_EXTENSION;
    }

    public function isEarlyReturnItem(): bool
    {
        return $this->item_type === self::ITEM_TYPE_EARLY_RETURN;
    }

    public function isCancellationItem(): bool
    {
        return $this->item_type === self::ITEM_TYPE_CANCELLATION;
    }

    public function isTaxItem(): bool
    {
        return $this->item_type === self::ITEM_TYPE_TAX;
    }

    public function isFeeItem(): bool
    {
        return $this->item_type === self::ITEM_TYPE_FEE;
    }
}
