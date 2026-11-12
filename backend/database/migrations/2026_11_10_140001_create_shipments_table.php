<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();

            // Tanpa UNIQUE: desain partial shipment disiapkan (docs/02 §9).
            $table->foreignId('seller_order_id')->constrained('seller_orders')->restrictOnDelete();
            $table->foreignId('seller_id')->constrained('sellers')->restrictOnDelete();

            $table->string('provider', 30)->default('manual');
            $table->string('service_code', 50)->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->string('provider_shipment_id', 191)->nullable();
            $table->string('status', 20)->default('pending');

            $table->bigInteger('shipping_cost')->default(0);
            $table->decimal('weight_gram', 10, 2)->nullable();
            $table->string('origin_city', 100)->nullable();
            $table->string('destination_city', 100)->nullable();
            $table->string('label_path', 255)->nullable();

            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->unique('tracking_number', 'shipments_tracking_unique');
            $table->index(['seller_order_id'], 'shipments_seller_order_idx');
            $table->index(['seller_id', 'status'], 'shipments_seller_status_idx');
        });

        DB::statement('ALTER TABLE shipments ADD CONSTRAINT chk_shipments_cost_nonneg CHECK (shipping_cost >= 0)');
        DB::statement('ALTER TABLE shipments ADD CONSTRAINT chk_shipments_weight_nonneg CHECK (weight_gram IS NULL OR weight_gram >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
