<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('scope', 20)->default('global');
            $table->foreignId('category_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->restrictOnDelete();
            $table->bigInteger('rate_bp');
            $table->boolean('includes_shipping')->default(false);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['scope', 'is_active'], 'commission_rules_scope_active_idx');
        });

        DB::statement('ALTER TABLE commission_rules ADD CONSTRAINT chk_rule_rate_range CHECK (rate_bp >= 0 AND rate_bp <= 10000)');
        DB::statement("ALTER TABLE commission_rules ADD CONSTRAINT chk_rule_scope_target CHECK ((scope = 'global' AND category_id IS NULL AND seller_id IS NULL) OR (scope = 'category' AND category_id IS NOT NULL AND seller_id IS NULL) OR (scope = 'seller' AND seller_id IS NOT NULL AND category_id IS NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_rules');
    }
};
