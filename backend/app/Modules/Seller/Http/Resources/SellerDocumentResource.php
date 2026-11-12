<?php

namespace App\Modules\Seller\Http\Resources;

use App\Enums\SellerDocumentStatus;
use App\Enums\SellerDocumentType;
use App\Modules\Seller\Models\SellerDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SellerDocument
 */
class SellerDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canManage = (bool) $request->user()?->can('manage_sellers');

        return [
            'id' => $this->id,
            'type' => $this->type instanceof SellerDocumentType ? $this->type->value : $this->type,
            'type_label' => $this->type instanceof SellerDocumentType ? $this->type->label() : null,
            'status' => $this->status instanceof SellerDocumentStatus ? $this->status->value : $this->status,
            'review_note' => $this->review_note,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            // URL unduh hanya untuk admin yang berhak (dokumen bersifat sensitif).
            'download_url' => $canManage
                ? route('admin.seller-documents.download', ['document' => $this->id])
                : null,
        ];
    }
}
