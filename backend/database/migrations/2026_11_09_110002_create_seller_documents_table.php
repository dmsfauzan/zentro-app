<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->restrictOnDelete();
            $table->string('type', 30);
            $table->string('file_path');
            $table->string('file_hash', 64)->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['seller_id', 'type', 'file_hash'], 'seller_documents_dedup_unique');
            $table->index(['seller_id', 'type'], 'seller_documents_seller_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_documents');
    }
};
