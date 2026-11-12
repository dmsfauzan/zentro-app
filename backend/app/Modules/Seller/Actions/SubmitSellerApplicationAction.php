<?php

namespace App\Modules\Seller\Actions;

use App\Enums\SellerAddressType;
use App\Enums\SellerStatus;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Seller\Models\Seller;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Membuat aplikasi seller (status awal: pending) beserta alamat asal.
 *
 * Aturan:
 * - satu user hanya boleh memiliki satu toko;
 * - email harus sudah terverifikasi.
 */
final class SubmitSellerApplicationAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, array $data): Seller
    {
        if ($user->email_verified_at === null) {
            throw new ApiException(
                'Email harus diverifikasi sebelum mendaftar sebagai penjual.',
                403,
                ErrorCode::EmailNotVerified,
            );
        }

        if ($user->seller()->exists()) {
            throw new ApiException(
                'Anda sudah memiliki toko.',
                409,
                ErrorCode::SellerAlreadyExists,
            );
        }

        return DB::transaction(function () use ($user, $data): Seller {
            $seller = Seller::create([
                'user_id' => $user->getKey(),
                'store_name' => $data['store_name'],
                'slug' => $this->uniqueSlug($data['store_name']),
                'description' => $data['description'] ?? null,
                'status' => SellerStatus::Pending,
            ]);

            $seller->addresses()->create([
                ...$data['address'],
                'type' => SellerAddressType::Origin,
                'is_default' => true,
            ]);

            AuditLog::record(
                action: 'seller.application_submitted',
                entityType: 'seller',
                entityId: $seller->getKey(),
                after: ['store_name' => $seller->store_name, 'status' => $seller->status->value],
                actorId: $user->getKey(),
            );

            return $seller->load(['addresses', 'documents']);
        });
    }

    private function uniqueSlug(string $storeName): string
    {
        $base = Str::slug($storeName);

        if ($base === '') {
            $base = 'toko';
        }

        $slug = $base;
        $suffix = 1;

        while (Seller::query()->where('slug', $slug)->exists()) {
            $suffix++;
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }
}
