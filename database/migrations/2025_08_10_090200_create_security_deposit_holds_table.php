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
        Schema::create('security_deposit_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('users')->onDelete('cascade');
            $table->decimal('amount', 8, 2)->default(250.00); // Standard $250 security deposit
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('stripe_charge_id')->nullable();
            $table->enum('status', ['pending', 'active', 'partially_released', 'fully_released', 'failed'])->default('pending');
            $table->timestamp('hold_date')->nullable();
            $table->timestamp('release_date')->nullable();
            $table->decimal('released_amount', 8, 2)->default(0);
            $table->decimal('withheld_amount', 8, 2)->default(0);
            $table->enum('release_reason', ['rental_completed', 'incident_charge', 'partial_incident', 'admin_override'])->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['rental_id', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index(['status', 'hold_date']);
            $table->index('stripe_payment_intent_id');
            $table->index('stripe_charge_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_deposit_holds');
    }
};
