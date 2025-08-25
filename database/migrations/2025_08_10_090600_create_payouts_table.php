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
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->onDelete('cascade');
            $table->foreignId('car_owner_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('bank_connection_id')->constrained()->onDelete('cascade');
            $table->foreignId('bank_account_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->decimal('commission_amount', 8, 2);
            $table->decimal('net_amount', 10, 2);
            $table->enum('status', ['pending', 'scheduled', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->string('plaid_transfer_id')->nullable();
            $table->string('stripe_payout_id')->nullable();
            $table->date('scheduled_date')->nullable();
            $table->timestamp('processed_date')->nullable();
            $table->enum('failure_reason', ['insufficient_funds', 'account_closed', 'invalid_account', 'bank_error', 'network_error', 'other'])->nullable();
            $table->integer('retry_count')->default(0);
            $table->integer('max_retries')->default(3);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['rental_id', 'status']);
            $table->index(['car_owner_id', 'status']);
            $table->index(['status', 'scheduled_date']);
            $table->index(['status', 'processed_date']);
            $table->index('plaid_transfer_id');
            $table->index('stripe_payout_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
