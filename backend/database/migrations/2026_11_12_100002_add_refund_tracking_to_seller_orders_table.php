<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 8A — pelacakan refund kumulatif per sub-order.
 *
 * Dipakai untuk invariant "satu sub-order tidak boleh direfund melebihi sisa"
 * dan untuk menghitung delta debit seller secara proporsional (anti pembulatan
 * menumpuk pada refund bertahap).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_orders', function (Blueprint $table): void {
            $table->bigInteger('refunded_product_amount')->default(0)->after('platform_funded_discount');
            $table->bigInteger('refunded_shipping_amount')->default(0)->after('refunded_product_amount');
            $table->bigInteger('seller_refunded_amount')->default(0)->after('refunded_shipping_amount');
            $table->bigInteger('commission_reversed_amount')->default(0)->after('seller_refunded_amount');
        });
    }

    public function down(): void
    {
        Schema::table('seller_orders', function (Blueprint $table): void {
            $table->dropColumn([
                'refunded_product_amount',
                'refunded_shipping_amount',
                'seller_refunded_amount',
                'commission_reversed_amount',
            ]);
        });
    }
};
