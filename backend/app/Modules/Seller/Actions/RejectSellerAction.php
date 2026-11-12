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
 * Menolak aplikasi seller: pending|under_review → rejected.
 */
final class RejectSellerAction
{
    public function execute(User $admin, Seller $seller, string $reason): Seller
    {
        $allowed = [SellerStatus::Pending, SellerStatus::UnderReview];

        if (! in_array($seller->status, $allowed, true)) {
            throw new ApiException(
                'Transisi status tidak sah. Hanya seller "pending"/"under_review" yang dapat ditolak.',
                409,
                ErrorCode::StateTransitionInvalid,
            );
        }

        return DB::transaction(function () use ($admin, $seller, $reason): Seller {
            $previous = $seller->status;

            $seller->forceFill([
                'status' => SellerStatus::Rejected,
                'rejected_at' => now(),
                'rejected_by' => $admin->getKey(),
                'rejection_reason' => $reason,
            ])->save();

            AuditLog::record(
                action: 'seller.rejected',
                entityType: 'seller',
                entityId: $seller->getKey(),
                before: ['status' => $previous->value],
                after: ['status' => SellerStatus::Rejected->value, 'reason' => $reason],
                actorId: $admin->getKey(),
            );

            return $seller->load(['addresses', 'documents', 'user']);
        });
    }
}
