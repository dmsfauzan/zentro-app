<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->string('provider', 30);
            $table->string('provider_reference', 191)->nullable();
            $table->string('status', 20)->default('initiated');
            $table->bigInteger('amount');

            // Payload disimpan setelah redaksi field sensitif.
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();

            $table->string('failure_code', 100)->nullable();
            $table->text('failure_message')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['payment_id', 'created_at'], 'payment_attempts_payment_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
