<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_payouts', function (Blueprint $table) {
            $table->id();
            $table->string('payout_number', 40)->unique('seller_payouts_number_unique');
            $table->foreignId('seller_id')->constrained('sellers')->restrictOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained('seller_bank_accounts')->restrictOnDelete();

            $table->bigInteger('amount');
            $table->string('status', 20)->default('requested');

            // Snapshot rekening (nomor dienkripsi).
            $table->string('bank_name_snapshot', 100);
            $table->text('bank_account_number_snapshot');
            $table->string('bank_account_name_snapshot', 150);

            $table->string('provider_reference', 191)->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            $table->index(['seller_id', 'status', 'created_at'], 'payouts_seller_status_idx');
            $table->index(['status', 'created_at'], 'payouts_status_created_idx');
        });

        DB::statement('ALTER TABLE seller_payouts ADD CONSTRAINT chk_payout_amount_positive CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_payouts');
    }
};
