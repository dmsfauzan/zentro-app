<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained('vouchers')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('seller_order_id')->nullable()->constrained('seller_orders')->restrictOnDelete();
            $table->bigInteger('discount_amount');
            $table->timestamp('used_at')->useCurrent();

            $table->unique(['voucher_id', 'order_id'], 'voucher_usages_voucher_order_unique');
            $table->index(['user_id', 'voucher_id'], 'voucher_usages_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_usages');
    }
};
