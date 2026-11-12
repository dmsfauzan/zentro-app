<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->restrictOnDelete();
            $table->string('status', 20);
            $table->string('description', 255)->nullable();
            $table->string('location', 150)->nullable();

            // Waktu kejadian dari provider/seller (bisa beda dengan created_at).
            $table->timestamp('occurred_at');

            // Kunci idempotensi timeline dari provider (NULL aman untuk entri manual).
            $table->string('provider_event_id', 191)->nullable();

            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique('provider_event_id', 'shipment_hist_provider_event_unique');
            $table->index(['shipment_id', 'occurred_at'], 'shipment_hist_shipment_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_histories');
    }
};
