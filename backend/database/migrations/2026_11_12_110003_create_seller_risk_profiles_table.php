<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 8B — profil risiko seller menentukan reserve & masa hold.
 *
 * Default rate 0% & hold 0 hari → perilaku lama tidak berubah sampai admin
 * menetapkan profil berisiko.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_risk_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_id')->unique('seller_risk_profiles_seller_unique')->constrained('sellers')->restrictOnDelete();
            $table->unsignedInteger('reserve_rate_bp')->default(0);
            $table->unsignedInteger('hold_days')->default(0);
            $table->string('reason', 191)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_risk_profiles');
    }
};
