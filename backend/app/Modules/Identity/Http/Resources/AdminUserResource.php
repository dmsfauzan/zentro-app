<?php

namespace App\Modules\Identity\Http\Resources;

use App\Enums\UserStatus;
use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class AdminUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'email_verified' => $this->email_verified_at !== null,
            'status' => $this->status instanceof UserStatus ? $this->status->value : $this->status,
            'roles' => $this->getRoleNames()->values()->all(),
            'seller' => $this->whenLoaded('seller', fn (): ?array => $this->seller === null ? null : [
                'id' => $this->seller->id,
                'store_name' => $this->seller->store_name,
                'status' => $this->seller->status instanceof \BackedEnum
                    ? $this->seller->status->value
                    : $this->seller->status,
            ]),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
