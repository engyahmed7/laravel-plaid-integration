<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rentals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_owner_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('car_owner_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('actual_return_date')->nullable();
            $table->decimal('daily_rate', 8, 2);
            $table->decimal('total_amount', 10, 2);
            $table->decimal('rap_daily_rate', 8, 2)->default(0);
            $table->integer('rap_days')->default(0);
            $table->decimal('rap_total', 8, 2)->default(0);
            // Removed security_deposit_hold_id to avoid circular dependency
            // Security deposit holds will reference rentals instead
            $table->enum('status', ['pending', 'active', 'completed', 'cancelled', 'extended'])->default('pending');
            $table->enum('billing_cycle', ['weekly', 'monthly'])->default('weekly');
            $table->date('next_billing_date')->nullable();
            $table->date('last_billed_date')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->integer('extension_days')->default(0);
            $table->integer('early_return_days')->default(0);
            $table->decimal('incident_charges', 8, 2)->default(0);
            $table->decimal('commission_rate', 6, 4)->default(0.1500); // 15% default commission
            $table->decimal('commission_amount', 8, 2)->default(0);
            $table->decimal('payout_amount', 8, 2)->default(0);
            $table->enum('payout_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->date('payout_date')->nullable();
            $table->timestamps();

            $table->index(['shop_owner_id', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index(['car_owner_id', 'status']);
            $table->index(['vehicle_id', 'status']);
            $table->index(['status', 'next_billing_date']);
            $table->index(['payout_status', 'payout_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rentals');
    }
};
