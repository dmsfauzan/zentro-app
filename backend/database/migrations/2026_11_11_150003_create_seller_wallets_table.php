<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->unique()->constrained('sellers')->restrictOnDelete();

            // BIGINT signed: available boleh negatif saat refund pasca-payout (utang).
            $table->bigInteger('pending_balance')->default(0);
            $table->bigInteger('available_balance')->default(0);
            $table->bigInteger('paid_balance')->default(0);
            $table->dateTime('balance_updated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_wallets');
    }
};
