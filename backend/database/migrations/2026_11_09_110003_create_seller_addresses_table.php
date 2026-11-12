<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->string('type', 20)->default('origin');
            $table->string('label', 50)->nullable();
            $table->string('contact_name', 150);
            $table->string('phone', 30);
            $table->text('address_line');
            $table->string('province', 100);
            $table->string('city', 100);
            $table->string('district', 100)->nullable();
            $table->string('village', 100)->nullable();
            $table->string('postal_code', 10);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_default')->default(false);

            // Hanya satu alamat default per seller.
            $table->tinyInteger('default_uniq')->nullable()->storedAs('IF(is_default = 1, 1, NULL)');

            $table->timestamps();

            $table->unique(['seller_id', 'default_uniq'], 'seller_addresses_default_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_addresses');
    }
};
