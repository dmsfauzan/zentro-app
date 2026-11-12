<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 8B — reserve risiko per sub-order saat settlement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_orders', function (Blueprint $table): void {
            $table->bigInteger('risk_hold_amount')->default(0)->after('commission_reversed_amount');
            $table->timestamp('risk_hold_release_at')->nullable()->after('risk_hold_amount');
            $table->timestamp('risk_hold_released_at')->nullable()->after('risk_hold_release_at');

            $table->index(['risk_hold_released_at', 'risk_hold_release_at'], 'seller_orders_risk_hold_idx');
        });
    }

    public function down(): void
    {
        Schema::table('seller_orders', function (Blueprint $table): void {
            $table->dropIndex('seller_orders_risk_hold_idx');
            $table->dropColumn(['risk_hold_amount', 'risk_hold_release_at', 'risk_hold_released_at']);
        });
    }
};
