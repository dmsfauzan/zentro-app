<?php

namespace App\Modules\Seller\Http\Resources;

use App\Enums\SellerAddressType;
use App\Modules\Seller\Models\SellerAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SellerAddress
 */
class SellerAddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type instanceof SellerAddressType ? $this->type->value : $this->type,
            'label' => $this->label,
            'contact_name' => $this->contact_name,
            'phone' => $this->phone,
            'address_line' => $this->address_line,
            'province' => $this->province,
            'city' => $this->city,
            'district' => $this->district,
            'village' => $this->village,
            'postal_code' => $this->postal_code,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'is_default' => (bool) $this->is_default,
        ];
    }
}
