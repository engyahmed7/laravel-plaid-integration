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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_owner_id')->constrained('users')->onDelete('cascade');
            $table->string('make');
            $table->string('model');
            $table->integer('year');
            $table->string('license_plate')->unique();
            $table->string('vin')->unique();
            $table->enum('vehicle_type', ['economy', 'standard', 'premium', 'luxury'])->default('standard');
            $table->decimal('daily_rate', 8, 2);
            $table->decimal('rap_daily_rate', 8, 2);
            $table->enum('status', ['available', 'rented', 'maintenance', 'out_of_service'])->default('available');
            $table->text('location_address')->nullable();
            $table->string('location_city')->nullable();
            $table->string('location_state')->nullable();
            $table->string('location_zip')->nullable();
            $table->json('features')->nullable();
            $table->text('insurance_info')->nullable();
            $table->date('registration_expiry')->nullable();
            $table->date('inspection_expiry')->nullable();
            $table->integer('mileage')->default(0);
            $table->decimal('fuel_level', 5, 2)->default(100.00);
            $table->text('condition_notes')->nullable();
            $table->timestamps();

            $table->index(['car_owner_id', 'status']);
            $table->index(['vehicle_type', 'status']);
            $table->index('license_plate');
            $table->index('vin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
