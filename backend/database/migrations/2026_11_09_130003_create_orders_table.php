<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 32)->unique();
            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();

            // FK ke `vouchers` ditambahkan bersama pembuatan tabel vouchers (fase voucher).
            $table->unsignedBigInteger('voucher_id')->nullable();

            $table->string('status', 24)->default('pending_payment');
            $table->bigInteger('subtotal')->default(0);
            $table->bigInteger('discount_total')->default(0);
            $table->bigInteger('shipping_total')->default(0);
            $table->bigInteger('grand_total')->default(0);
            $table->char('currency', 3)->default('IDR');

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->boolean('is_reviewed')->default(false);

            $table->timestamps();

            $table->index(['buyer_id', 'created_at'], 'orders_buyer_created_idx');
            $table->index(['status', 'expires_at'], 'orders_status_expires_idx');
            $table->index(['status', 'created_at'], 'orders_status_created_idx');
        });

        DB::statement('ALTER TABLE orders ADD CONSTRAINT chk_orders_amounts_nonneg CHECK (subtotal >= 0 AND discount_total >= 0 AND shipping_total >= 0 AND grand_total >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
