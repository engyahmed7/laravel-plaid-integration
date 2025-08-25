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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_connection_id')->constrained()->onDelete('cascade');
            $table->foreignId('bank_account_id')->constrained()->onDelete('cascade');
            $table->string('plaid_transaction_id')->unique();
            $table->string('stripe_transaction_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency_code', 3)->default('USD');
            $table->string('description');
            $table->string('merchant_name')->nullable();
            $table->string('category')->nullable();
            $table->string('subcategory')->nullable();
            $table->string('transaction_type')->nullable();
            $table->date('transaction_date');
            $table->date('authorized_date')->nullable();
            $table->string('account_owner')->nullable();
            $table->string('location_address')->nullable();
            $table->string('location_city')->nullable();
            $table->string('location_region')->nullable();
            $table->string('location_postal_code')->nullable();
            $table->string('location_country')->nullable();
            $table->string('payment_channel')->nullable();
            $table->boolean('pending')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['bank_connection_id', 'transaction_date']);
            $table->index(['bank_account_id', 'transaction_date']);
            $table->index('plaid_transaction_id');
            $table->index(['pending', 'transaction_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
