<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'car_owner_id',
        'make',
        'model',
        'year',
        'license_plate',
        'vin',
        'vehicle_type',
        'daily_rate',
        'rap_daily_rate',
        'status',
        'location_address',
        'location_city',
        'location_state',
        'location_zip',
        'features',
        'insurance_info',
        'registration_expiry',
        'inspection_expiry',
        'mileage',
        'fuel_level',
        'condition_notes',
    ];

    protected $casts = [
        'daily_rate' => 'decimal:2',
        'rap_daily_rate' => 'decimal:2',
        'year' => 'integer',
        'mileage' => 'integer',
        'fuel_level' => 'decimal:2',
        'registration_expiry' => 'date',
        'inspection_expiry' => 'date',
        'features' => 'array',
    ];

    // Vehicle types
    const TYPE_ECONOMY = 'economy';
    const TYPE_STANDARD = 'standard';
    const TYPE_PREMIUM = 'premium';
    const TYPE_LUXURY = 'luxury';

    // Vehicle statuses
    const STATUS_AVAILABLE = 'available';
    const STATUS_RENTED = 'rented';
    const STATUS_MAINTENANCE = 'maintenance';
    const STATUS_OUT_OF_SERVICE = 'out_of_service';

    public function carOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'car_owner_id');
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    public function activeRental(): BelongsTo
    {
        return $this->belongsTo(Rental::class, 'id', 'vehicle_id')
            ->where('status', Rental::STATUS_ACTIVE);
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE;
    }

    public function isRented(): bool
    {
        return $this->status === self::STATUS_RENTED;
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->year} {$this->make} {$this->model}";
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->getFullNameAttribute()} ({$this->license_plate})";
    }

    public function getLocationDisplayAttribute(): string
    {
        $parts = array_filter([
            $this->location_city,
            $this->location_state,
            $this->location_zip
        ]);

        return implode(', ', $parts);
    }

    public function getStandardDailyRateAttribute(): float
    {
        // RNTLS standard rates based on vehicle type
        return match ($this->vehicle_type) {
            self::TYPE_ECONOMY => 40.00,
            self::TYPE_STANDARD => 50.00,
            self::TYPE_PREMIUM => 60.00,
            self::TYPE_LUXURY => 80.00,
            default => 50.00,
        };
    }

    public function getStandardRapDailyRateAttribute(): float
    {
        // RNTLS standard RAP rates based on vehicle type
        return match ($this->vehicle_type) {
            self::TYPE_ECONOMY => 5.00,
            self::TYPE_STANDARD => 6.00,
            self::TYPE_PREMIUM => 7.00,
            self::TYPE_LUXURY => 10.00,
            default => 6.00,
        };
    }
}
