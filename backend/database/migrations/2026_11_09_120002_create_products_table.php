<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('name', 200);
            $table->string('slug', 191)->unique();
            $table->longText('description')->nullable();
            $table->decimal('weight_gram', 10, 2)->nullable();
            $table->string('status', 20)->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->unsignedInteger('sold_count')->default(0);
            $table->bigInteger('min_price')->nullable();
            $table->bigInteger('max_price')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['seller_id', 'status'], 'products_seller_status_idx');
            $table->index(['category_id', 'status'], 'products_category_status_idx');
            $table->index(['status', 'created_at'], 'products_status_created_idx');
            $table->index(['status', 'min_price'], 'products_status_price_idx');
            $table->index(['status', 'sold_count'], 'products_status_sold_idx');
        });

        DB::statement('ALTER TABLE products ADD CONSTRAINT chk_products_prices_nonneg CHECK (min_price IS NULL OR min_price >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT chk_products_maxprice_nonneg CHECK (max_price IS NULL OR max_price >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
