<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Deviasi terdokumentasi dari docs/03: webhook shipping butuh penyimpanan
        // durable sebelum diproses (docs/06 §13). Mencerminkan payment_webhook_events.
        Schema::create('shipping_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);
            $table->string('provider_event_id', 191)->unique('shipping_webhook_provider_event_unique');
            $table->string('event_type', 100);
            $table->string('provider_shipment_id', 191)->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->string('status', 20)->default('received');
            $table->json('payload');
            $table->string('signature', 255)->nullable();
            $table->text('processing_error')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();

            $table->index(['status', 'received_at'], 'shipping_webhook_status_received_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_webhook_events');
    }
};
