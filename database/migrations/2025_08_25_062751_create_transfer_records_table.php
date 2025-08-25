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
        Schema::create('transfer_records', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_transfer_id')->unique();
            $table->foreignId('connected_account_id')->constrained();
            $table->integer('amount_cents');
            $table->string('currency', 3)->default('usd');
            $table->string('status');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('transferred_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_records');
    }
};
