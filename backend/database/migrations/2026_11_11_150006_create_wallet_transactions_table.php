<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->restrictOnDelete();
            $table->foreignId('wallet_id')->constrained('seller_wallets')->restrictOnDelete();
            $table->foreignId('seller_order_id')->nullable()->constrained('seller_orders')->restrictOnDelete();
            $table->foreignId('payout_id')->nullable()->constrained('seller_payouts')->restrictOnDelete();
            $table->foreignId('refund_id')->nullable()->constrained('refunds')->restrictOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->constrained('wallet_transactions')->restrictOnDelete();

            $table->string('type', 32);
            $table->string('balance_bucket', 12);
            $table->bigInteger('amount');
            $table->bigInteger('balance_before');
            $table->bigInteger('balance_after');
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['seller_id', 'created_at'], 'wallet_tx_seller_created_idx');
            $table->index(['seller_id', 'balance_bucket', 'created_at'], 'wallet_tx_bucket_created_idx');
            $table->index(['reference_type', 'reference_id'], 'wallet_tx_reference_idx');
        });

        DB::statement('ALTER TABLE wallet_transactions ADD CONSTRAINT chk_wallet_tx_balance CHECK (balance_after = balance_before + amount)');
        DB::statement("ALTER TABLE wallet_transactions ADD CONSTRAINT chk_wallet_tx_bucket CHECK (balance_bucket IN ('pending','available','paid'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
