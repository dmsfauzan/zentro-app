<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);

            // Kunci idempotensi: satu event provider hanya diproses sekali.
            $table->string('provider_event_id', 191)->unique('webhook_provider_event_unique');

            $table->string('event_type', 100);
            $table->string('provider_reference', 191)->nullable();
            $table->string('status', 20)->default('received');
            $table->json('payload');
            $table->string('signature', 255)->nullable();
            $table->text('processing_error')->nullable();

            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();

            $table->index(['status', 'received_at'], 'webhook_status_received_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
    }
};
