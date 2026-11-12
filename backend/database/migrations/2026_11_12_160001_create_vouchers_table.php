<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique('vouchers_code_unique');
            $table->string('name', 150);
            $table->string('type', 10)->default('fixed');
            $table->bigInteger('value');
            $table->bigInteger('min_purchase')->default(0);
            $table->bigInteger('max_discount')->nullable();
            $table->string('scope', 10)->default('platform');
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->restrictOnDelete();
            $table->string('funded_by', 10)->default('platform');
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_limit_per_user')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at');
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'expires_at'], 'vouchers_active_window_idx');
        });

        DB::statement("ALTER TABLE vouchers ADD CONSTRAINT chk_voucher_value CHECK (value > 0 AND (type <> 'percent' OR value <= 10000))");
        DB::statement('ALTER TABLE vouchers ADD CONSTRAINT chk_voucher_dates CHECK (expires_at IS NULL OR expires_at > starts_at)');
        DB::statement("ALTER TABLE vouchers ADD CONSTRAINT chk_voucher_scope_target CHECK (scope = 'platform' OR (scope = 'seller' AND seller_id IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
