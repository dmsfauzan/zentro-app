<?php

namespace App\Modules\Identity\Http\Resources;

use App\Enums\UserStatus;
use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'firebase_uid' => $this->firebase_uid,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified' => $this->email_verified_at !== null,
            'phone' => $this->phone,
            'status' => $this->status instanceof UserStatus ? $this->status->value : $this->status,
            'roles' => $this->getRoleNames()->values()->all(),
            'permissions' => $this->getAllPermissions()->pluck('name')->values()->all(),
            // Diisi pada Phase 2 (Seller Management).
            'seller' => null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
