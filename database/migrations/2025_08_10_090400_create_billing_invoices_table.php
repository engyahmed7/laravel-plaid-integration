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
        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->onDelete('cascade');
            $table->foreignId('shop_owner_id')->constrained('users')->onDelete('cascade');
            $table->string('invoice_number')->unique();
            $table->date('billing_period_start');
            $table->date('billing_period_end');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax_amount', 8, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->enum('status', ['draft', 'pending', 'paid', 'overdue', 'cancelled', 'failed'])->default('draft');
            $table->string('stripe_invoice_id')->nullable();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->date('due_date');
            $table->text('notes')->nullable();
            $table->enum('billing_cycle', ['weekly', 'monthly'])->default('weekly');
            $table->timestamps();

            $table->index(['rental_id', 'status']);
            $table->index(['shop_owner_id', 'status']);
            $table->index(['status', 'due_date']);
            $table->index(['billing_period_start', 'billing_period_end']);
            $table->index('stripe_invoice_id');
            $table->index('stripe_payment_intent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_invoices');
    }
};
