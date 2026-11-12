<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_order_id')->unique()->constrained('seller_orders')->restrictOnDelete();
            $table->foreignId('seller_id')->constrained('sellers')->restrictOnDelete();
            $table->foreignId('commission_rule_id')->nullable()->constrained('commission_rules')->nullOnDelete();
            $table->bigInteger('base_amount');
            $table->bigInteger('rate_bp');
            $table->bigInteger('commission_amount');
            $table->bigInteger('rounding_adjustment')->default(0);
            $table->string('status', 20)->default('pending');
            $table->timestamp('earned_at')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'status', 'created_at'], 'commissions_seller_status_idx');
        });

        DB::statement('ALTER TABLE commissions ADD CONSTRAINT chk_comm_rate_range CHECK (rate_bp >= 0 AND rate_bp <= 10000)');
        DB::statement('ALTER TABLE commissions ADD CONSTRAINT chk_comm_amounts_nonneg CHECK (base_amount >= 0 AND commission_amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
