<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 8B — pemisahan saldo: `risk_hold` (reserve risiko) dan `debt` (utang seller).
 *
 * `available` tidak lagi boleh negatif untuk merepresentasikan utang; utang
 * dicatat eksplisit di bucket `debt`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_wallets', function (Blueprint $table): void {
            $table->bigInteger('risk_hold_balance')->default(0)->after('payout_reserved_balance');
            $table->bigInteger('debt_balance')->default(0)->after('risk_hold_balance');
        });

        DB::statement('ALTER TABLE wallet_transactions DROP CHECK chk_wallet_tx_bucket');
        DB::statement("ALTER TABLE wallet_transactions ADD CONSTRAINT chk_wallet_tx_bucket CHECK (balance_bucket IN ('pending','available','payout_reserved','risk_hold','debt','paid'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE wallet_transactions DROP CHECK chk_wallet_tx_bucket');
        DB::statement("ALTER TABLE wallet_transactions ADD CONSTRAINT chk_wallet_tx_bucket CHECK (balance_bucket IN ('pending','available','payout_reserved','paid'))");

        Schema::table('seller_wallets', function (Blueprint $table): void {
            $table->dropColumn(['risk_hold_balance', 'debt_balance']);
        });
    }
};
