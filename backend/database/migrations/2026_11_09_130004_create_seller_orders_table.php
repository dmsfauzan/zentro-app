<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('seller_id')->constrained('sellers')->restrictOnDelete();
            $table->string('seller_order_number', 40)->unique();
            $table->string('status', 24)->default('pending');

            $table->bigInteger('subtotal')->default(0);
            $table->bigInteger('discount_total')->default(0);
            $table->bigInteger('shipping_cost')->default(0);
            $table->bigInteger('grand_total')->default(0);

            // Snapshot perhitungan komisi (diisi penuh pada fase finance).
            $table->bigInteger('commission_base_amount')->default(0);
            $table->bigInteger('commission_rate_bp')->default(0);
            $table->bigInteger('commission_amount')->default(0);
            $table->bigInteger('seller_net_amount')->default(0);
            $table->bigInteger('voucher_discount_borne_by_seller')->default(0);
            $table->bigInteger('platform_funded_discount')->default(0);

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->unique(['order_id', 'seller_id'], 'seller_orders_order_seller_unique');
            $table->index(['seller_id', 'status', 'created_at'], 'seller_orders_seller_status_created_idx');
        });

        DB::statement('ALTER TABLE seller_orders ADD CONSTRAINT chk_so_amounts_nonneg CHECK (subtotal >= 0 AND discount_total >= 0 AND shipping_cost >= 0 AND grand_total >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_orders');
    }
};
