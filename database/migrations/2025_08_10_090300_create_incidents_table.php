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
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('users')->onDelete('cascade');
            $table->enum('incident_type', ['towing', 'tire_replacement', 'damage', 'fuel', 'lockout', 'jump_start', 'other']);
            $table->text('description');
            $table->text('location')->nullable();
            $table->timestamp('incident_date');
            $table->decimal('amount', 8, 2);
            $table->enum('status', ['reported', 'under_review', 'approved', 'charged', 'refunded', 'dismissed'])->default('reported');
            $table->text('admin_notes')->nullable();
            $table->text('customer_notes')->nullable();
            $table->string('stripe_charge_id')->nullable();
            $table->string('stripe_refund_id')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['rental_id', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index(['incident_type', 'status']);
            $table->index(['status', 'incident_date']);
            $table->index('stripe_charge_id');
            $table->index('stripe_refund_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
