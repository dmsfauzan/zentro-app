<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Deviasi terdokumentasi dari docs/03: docs/06 memakai `bank_account_id`
        // pada payout, tetapi belum ada entitas rekening bank seller.
        Schema::create('seller_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->restrictOnDelete();
            $table->string('bank_name', 100);
            $table->text('account_number'); // encrypted cast
            $table->string('account_name', 150);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('seller_id', 'seller_bank_accounts_seller_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_bank_accounts');
    }
};
