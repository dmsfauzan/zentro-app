<?php

namespace App\Modules\Seller\Actions;

use App\Enums\SellerStatus;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Seller\Models\Seller;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use Illuminate\Support\Facades\DB;

/**
 * Mengaktifkan kembali seller yang ditangguhkan: suspended → active.
 */
final class ReactivateSellerAction
{
    public function execute(User $admin, Seller $seller): Seller
    {
        if ($seller->status !== SellerStatus::Suspended) {
            throw new ApiException(
                'Transisi status tidak sah. Hanya seller "suspended" yang dapat diaktifkan kembali.',
                409,
                ErrorCode::StateTransitionInvalid,
            );
        }

        return DB::transaction(function () use ($admin, $seller): Seller {
            $seller->forceFill([
                'status' => SellerStatus::Active,
                'suspended_at' => null,
                'suspension_reason' => null,
            ])->save();

            AuditLog::record(
                action: 'seller.reactivated',
                entityType: 'seller',
                entityId: $seller->getKey(),
                before: ['status' => SellerStatus::Suspended->value],
                after: ['status' => SellerStatus::Active->value],
                actorId: $admin->getKey(),
            );

            return $seller->load(['addresses', 'documents', 'user']);
        });
    }
}
