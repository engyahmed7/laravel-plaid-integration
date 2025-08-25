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
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_connection_id')->constrained()->onDelete('cascade');
            $table->string('plaid_account_id')->unique();
            $table->string('stripe_payment_method_id')->nullable();
            $table->string('stripe_external_account_id')->nullable();
            $table->string('account_name');
            $table->string('account_type');
            $table->string('account_subtype')->nullable();
            $table->string('mask')->nullable();
            $table->string('routing_number')->nullable();
            $table->decimal('balance_available', 10, 2)->nullable();
            $table->decimal('balance_current', 10, 2)->nullable();
            $table->decimal('balance_limit', 10, 2)->nullable();
            $table->string('currency_code', 3)->default('USD');
            $table->enum('verification_status', ['pending', 'verified', 'failed'])->default('pending');
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['bank_connection_id', 'is_active']);
            $table->index('plaid_account_id');
            $table->index('stripe_payment_method_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
