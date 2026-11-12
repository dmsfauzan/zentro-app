<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('seller_order_id')->constrained('seller_orders')->restrictOnDelete();
            $table->foreignId('seller_id')->constrained('sellers')->restrictOnDelete();

            // Snapshot: FK bersifat opsional agar produk/varian boleh soft delete.
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('variant_id')->nullable();

            $table->string('product_name_snapshot', 200);
            $table->string('sku_snapshot', 100);
            $table->string('variant_name_snapshot', 150)->nullable();
            $table->bigInteger('price_snapshot');
            $table->unsignedInteger('quantity');
            $table->bigInteger('discount_amount')->default(0);
            $table->bigInteger('line_total');
            $table->decimal('weight_gram_snapshot', 10, 2)->nullable();

            $table->timestamps();

            $table->index('seller_order_id', 'order_items_seller_order_idx');
            $table->index('product_id', 'order_items_product_idx');
        });

        DB::statement('ALTER TABLE order_items ADD CONSTRAINT chk_order_item_qty_positive CHECK (quantity > 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT chk_order_item_price_nonneg CHECK (price_snapshot >= 0 AND discount_amount >= 0 AND line_total >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
