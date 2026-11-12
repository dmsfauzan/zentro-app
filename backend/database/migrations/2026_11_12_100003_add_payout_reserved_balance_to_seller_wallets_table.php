<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 8A — reservasi saldo payout.
 *
 * Dana seller dipindahkan `available → payout_reserved` saat payout diajukan,
 * sehingga tidak bisa dipakai ulang. `mark_paid` baru memindahkan
 * `payout_reserved → paid` setelah transfer dikonfirmasi berhasil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_wallets', function (Blueprint $table): void {
            $table->bigInteger('payout_reserved_balance')->default(0)->after('available_balance');
        });

        // `payout_reserved` (15 karakter) tidak muat di varchar(12) sebelumnya.
        DB::statement('ALTER TABLE wallet_transactions MODIFY balance_bucket VARCHAR(24) NOT NULL');

        // Perluas CHECK bucket ledger (MySQL 8 menegakkan CHECK).
        DB::statement('ALTER TABLE wallet_transactions DROP CHECK chk_wallet_tx_bucket');
        DB::statement("ALTER TABLE wallet_transactions ADD CONSTRAINT chk_wallet_tx_bucket CHECK (balance_bucket IN ('pending','available','payout_reserved','paid'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE wallet_transactions DROP CHECK chk_wallet_tx_bucket');
        DB::statement("ALTER TABLE wallet_transactions ADD CONSTRAINT chk_wallet_tx_bucket CHECK (balance_bucket IN ('pending','available','paid'))");
        DB::statement('ALTER TABLE wallet_transactions MODIFY balance_bucket VARCHAR(12) NOT NULL');

        Schema::table('seller_wallets', function (Blueprint $table): void {
            $table->dropColumn('payout_reserved_balance');
        });
    }
};
