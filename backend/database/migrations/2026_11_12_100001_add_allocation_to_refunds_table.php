<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 8A — refund berbasis komponen.
 *
 * Refund kini menyimpan pecahan produk/ongkir, hasil alokasi ke seller, pembalik
 * komisi, serta `provider_refund_key` (idempotency key gateway) yang disimpan
 * SEBELUM memanggil provider sehingga retry tidak menjadi refund ganda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table): void {
            $table->bigInteger('product_amount')->default(0)->after('amount');
            $table->bigInteger('shipping_amount')->default(0)->after('product_amount');
            $table->bigInteger('seller_debit_amount')->default(0)->after('shipping_amount');
            $table->bigInteger('commission_reversal_amount')->default(0)->after('seller_debit_amount');
            $table->string('provider_refund_key', 64)->nullable()->after('provider_reference');
            $table->timestamp('provider_refunded_at')->nullable()->after('provider_refund_key');

            $table->index('provider_refund_key', 'refunds_provider_refund_key_idx');
        });

        // Backfill data lama (sebelum kolom ini ada): perlakukan seluruhnya sebagai produk.
        DB::table('refunds')
            ->where('product_amount', 0)
            ->where('shipping_amount', 0)
            ->update(['product_amount' => DB::raw('amount')]);
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table): void {
            $table->dropIndex('refunds_provider_refund_key_idx');
            $table->dropColumn([
                'product_amount',
                'shipping_amount',
                'seller_debit_amount',
                'commission_reversal_amount',
                'provider_refund_key',
                'provider_refunded_at',
            ]);
        });
    }
};
