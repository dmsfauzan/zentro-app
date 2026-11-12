<?php

namespace App\Modules\Seller\Http\Resources;

use App\Enums\SellerStatus;
use App\Modules\Seller\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Seller
 */
class SellerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'store_name' => $this->store_name,
            'slug' => $this->slug,
            'description' => $this->description,
            'logo_path' => $this->logo_path,
            'banner_path' => $this->banner_path,
            'status' => $this->status instanceof SellerStatus ? $this->status->value : $this->status,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'suspended_at' => $this->suspended_at?->toIso8601String(),
            'suspension_reason' => $this->suspension_reason,
            'rating_avg' => (float) $this->rating_avg,
            'rating_count' => (int) $this->rating_count,

            'kyc' => $this->whenLoaded('documents', function (): array {
                $missing = $this->missingRequiredDocuments();

                return [
                    'required' => config('zentro.kyc_required_documents', []),
                    'missing' => $missing,
                    'complete' => $missing === [],
                ];
            }),

            'addresses' => $this->whenLoaded(
                'addresses',
                fn () => SellerAddressResource::collection($this->addresses)->toArray($request),
            ),

            'documents' => $this->whenLoaded(
                'documents',
                fn () => SellerDocumentResource::collection($this->documents)->toArray($request),
            ),

            'owner' => $this->whenLoaded('user', fn (): array => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
            ]),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
