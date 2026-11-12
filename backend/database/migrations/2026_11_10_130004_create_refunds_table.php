<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->string('refund_number', 40)->unique('refunds_number_unique');
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('seller_order_id')->nullable()->constrained('seller_orders')->restrictOnDelete();
            $table->bigInteger('amount');
            $table->string('status', 20)->default('requested');
            $table->text('reason')->nullable();
            $table->string('provider_reference', 191)->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'refunds_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
