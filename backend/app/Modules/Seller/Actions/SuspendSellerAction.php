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
 * Menangguhkan seller: active → suspended.
 */
final class SuspendSellerAction
{
    public function execute(User $admin, Seller $seller, string $reason): Seller
    {
        if ($seller->status !== SellerStatus::Active) {
            throw new ApiException(
                'Transisi status tidak sah. Hanya seller aktif yang dapat ditangguhkan.',
                409,
                ErrorCode::StateTransitionInvalid,
            );
        }

        return DB::transaction(function () use ($admin, $seller, $reason): Seller {
            $seller->forceFill([
                'status' => SellerStatus::Suspended,
                'suspended_at' => now(),
                'suspension_reason' => $reason,
            ])->save();

            AuditLog::record(
                action: 'seller.suspended',
                entityType: 'seller',
                entityId: $seller->getKey(),
                before: ['status' => SellerStatus::Active->value],
                after: ['status' => SellerStatus::Suspended->value, 'reason' => $reason],
                actorId: $admin->getKey(),
            );

            return $seller->load(['addresses', 'documents', 'user']);
        });
    }
}
