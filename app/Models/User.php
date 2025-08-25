<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'stripe_customer_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function bankConnections()
    {
        return $this->hasMany(BankConnection::class);
    }

    public function bankAccounts()
    {
        return $this->hasManyThrough(BankAccount::class, BankConnection::class);
    }

    public function transactions()
    {
        return $this->hasManyThrough(Transaction::class, BankConnection::class);
    }

    public function shopRentals()
    {
        return $this->hasMany(Rental::class, 'shop_owner_id');
    }

    public function customerRentals()
    {
        return $this->hasMany(Rental::class, 'customer_id');
    }

    public function carOwnerRentals()
    {
        return $this->hasMany(Rental::class, 'car_owner_id');
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class, 'car_owner_id');
    }

    public function payouts()
    {
        return $this->hasMany(Payout::class, 'car_owner_id');
    }

    public function incidents()
    {
        return $this->hasMany(Incident::class, 'customer_id');
    }

    public function securityDepositHolds()
    {
        return $this->hasMany(SecurityDepositHold::class, 'customer_id');
    }

    public function billingInvoices()
    {
        return $this->hasManyThrough(BillingInvoice::class, Rental::class, 'shop_owner_id');
    }
}
