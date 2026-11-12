<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('sku', 100)->unique();
            $table->string('variant_name', 150);
            $table->bigInteger('price');
            $table->decimal('weight_gram', 10, 2)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['product_id', 'status'], 'product_variants_product_status_idx');
        });

        DB::statement('ALTER TABLE product_variants ADD CONSTRAINT chk_variant_price_nonneg CHECK (price >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
