<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Rental;
use App\Models\BillingInvoice;
use App\Models\InvoiceItem;
use Carbon\Carbon;

class CreateTestData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:test-data {user_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create test data for development';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('user_id') ?? 2;

        $this->info("Creating test data for user ID: {$userId}");

        // Find or create user
        $user = User::find($userId);
        if (!$user) {
            $this->error("User with ID {$userId} not found!");
            return 1;
        }

        // Create a vehicle
        $vehicle = Vehicle::create([
            'year' => 2023,
            'make' => 'Toyota',
            'model' => 'Camry',
            'vin' => 'TEST' . time(),
            'license_plate' => 'TEST' . time(),
            'color' => 'Silver',
            'mileage' => 15000,
            'daily_rate' => 50.00,
            'rap_daily_rate' => 10.00,
            'status' => 'available',
            'car_owner_id' => 1, // Assuming user ID 1 owns the vehicle
        ]);

        $this->info("Created vehicle: {$vehicle->year} {$vehicle->make} {$vehicle->model}");

        // Create a rental for the current user
        $rental = Rental::create([
            'shop_owner_id' => 1,
            'customer_id' => $userId, // This is the key - assign to current user
            'car_owner_id' => 1,
            'vehicle_id' => $vehicle->id,
            'start_date' => Carbon::now()->subDays(7),
            'end_date' => Carbon::now()->addDays(14),
            'daily_rate' => 50.00,
            'total_amount' => 1050.00,
            'rap_daily_rate' => 10.00,
            'rap_days' => 7,
            'rap_total' => 70.00,
            'status' => 'active',
            'billing_cycle' => 'weekly',
            'next_billing_date' => Carbon::now()->addDays(7),
            'commission_rate' => 0.15,
            'commission_amount' => 157.50,
            'payout_amount' => 942.50,
            'payout_status' => 'pending',
        ]);

        $this->info("Created rental ID: {$rental->id} for user ID: {$userId}");

        // Create a billing invoice
        $invoice = BillingInvoice::create([
            'rental_id' => $rental->id,
            'shop_owner_id' => 1,
            'invoice_number' => 'INV-' . str_pad($rental->id, 6, '0', STR_PAD_LEFT),
            'due_date' => Carbon::now()->addDays(7),
            'subtotal' => 350.00,
            'total_amount' => 350.00,
            'status' => 'pending',
            'billing_period_start' => Carbon::now()->subDays(7),
            'billing_period_end' => Carbon::now(),
        ]);

        $this->info("Created invoice: {$invoice->invoice_number}");

        // Create invoice items
        InvoiceItem::create([
            'billing_invoice_id' => $invoice->id,
            'description' => 'Weekly rental fee',
            'quantity' => 7,
            'unit_price' => 50.00,
            'total_price' => 350.00,
            'item_type' => 'rental',
        ]);

        $this->info("Created invoice item");

        $this->info("Test data created successfully!");
        $this->info("You can now test: /billing/rental/{$rental->id}");

        return 0;
    }
}
