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
        Schema::create('bank_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('plaid_access_token')->nullable();
            $table->string('plaid_item_id')->nullable();
            $table->string('plaid_public_token')->nullable();
            $table->string('stripe_customer_id')->nullable();
            $table->string('institution_name')->nullable();
            $table->string('institution_id')->nullable();
            $table->enum('status', ['active', 'inactive', 'error', 'pending'])->default('pending');
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('accounts_count')->default(0);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('plaid_item_id');
            $table->index('stripe_customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_connections');
    }
};
