<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('label', 50)->nullable();
            $table->string('recipient_name', 150);
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

            // Generated column agar hanya ada SATU alamat default per user.
            // NULL tidak akan bentrok pada UNIQUE, sehingga hanya baris is_default = 1 yang unik.
            $table->tinyInteger('default_uniq')->nullable()->storedAs('IF(is_default = 1, 1, NULL)');

            $table->timestamps();

            $table->unique(['user_id', 'default_uniq'], 'user_addresses_default_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_addresses');
    }
};
