<?php

namespace App\Modules\Seller\Actions;

use App\Enums\SellerDocumentStatus;
use App\Enums\SellerStatus;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Seller\Models\Seller;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Menyetujui aplikasi seller: under_review → active.
 *
 * Syarat: status under_review dan seluruh dokumen KYC wajib tersedia.
 */
final class ApproveSellerAction
{
    public function execute(User $admin, Seller $seller): Seller
    {
        if ($seller->status !== SellerStatus::UnderReview) {
            throw new ApiException(
                'Transisi status tidak sah. Hanya seller berstatus "under_review" yang dapat disetujui.',
                409,
                ErrorCode::StateTransitionInvalid,
            );
        }

        $seller->loadMissing('documents');
        $missing = $seller->missingRequiredDocuments();

        if ($missing !== []) {
            throw new ApiException(
                'Dokumen wajib belum lengkap.',
                422,
                ErrorCode::ValidationFailed,
                ['documents' => $missing],
            );
        }

        return DB::transaction(function () use ($admin, $seller): Seller {
            $seller->forceFill([
                'status' => SellerStatus::Active,
                'approved_at' => now(),
                'approved_by' => $admin->getKey(),
                'rejection_reason' => null,
                'rejected_at' => null,
                'rejected_by' => null,
            ])->save();

            $seller->documents()
                ->where('status', SellerDocumentStatus::Pending->value)
                ->update([
                    'status' => SellerDocumentStatus::Approved->value,
                    'reviewed_by' => $admin->getKey(),
                    'reviewed_at' => now(),
                ]);

            $this->assignSellerRole($seller);

            AuditLog::record(
                action: 'seller.approved',
                entityType: 'seller',
                entityId: $seller->getKey(),
                before: ['status' => SellerStatus::UnderReview->value],
                after: ['status' => SellerStatus::Active->value],
                actorId: $admin->getKey(),
            );

            return $seller->load(['addresses', 'documents', 'user']);
        });
    }

    private function assignSellerRole(Seller $seller): void
    {
        $user = $seller->user()->first();

        if ($user === null) {
            return;
        }

        if (Role::query()->where('name', 'seller')->where('guard_name', 'web')->exists()) {
            $user->assignRole('seller');
        }
    }
}
