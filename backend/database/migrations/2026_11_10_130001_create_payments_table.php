<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->restrictOnDelete();
            $table->string('provider', 30);

            // Identitas transaksi di provider (mis. `order_number` untuk Midtrans).
            $table->string('provider_reference', 191)->nullable();

            $table->string('payment_method', 50)->nullable();
            $table->string('status', 24)->default('pending');
            $table->bigInteger('amount');
            $table->char('currency', 3)->default('IDR');

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            // Snapshot aman dari provider (tanpa secret).
            $table->json('provider_payload')->nullable();

            $table->timestamps();

            $table->unique(['provider', 'provider_reference'], 'payments_provider_reference_unique');
            $table->index(['status', 'created_at'], 'payments_status_created_idx');
        });

        DB::statement('ALTER TABLE payments ADD CONSTRAINT chk_payments_amount_positive CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
