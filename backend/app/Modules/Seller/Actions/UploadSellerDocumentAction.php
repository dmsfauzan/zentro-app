<?php

namespace App\Modules\Seller\Actions;

use App\Enums\SellerDocumentStatus;
use App\Enums\SellerStatus;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Seller\Models\Seller;
use App\Modules\Seller\Models\SellerDocument;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan dokumen KYC seller pada disk privat.
 *
 * - Hanya boleh saat status pending / under_review / rejected.
 * - Duplikat (type + hash sama) ditolak.
 * - Unggahan pertama memindahkan status pending|rejected → under_review.
 */
final class UploadSellerDocumentAction
{
    public function execute(User $user, Seller $seller, string $type, UploadedFile $file): SellerDocument
    {
        if (! in_array($seller->status, SellerStatus::documentUploadable(), true)) {
            throw new ApiException(
                'Dokumen tidak dapat diunggah pada status toko saat ini.',
                409,
                ErrorCode::StateTransitionInvalid,
            );
        }

        $hash = hash_file('sha256', $file->getRealPath());

        if ($hash === false) {
            throw new ApiException('Berkas tidak dapat dibaca.', 422, ErrorCode::ValidationFailed);
        }

        $duplicate = $seller->documents()
            ->where('type', $type)
            ->where('file_hash', $hash)
            ->exists();

        if ($duplicate) {
            throw new ApiException(
                'Dokumen yang sama sudah pernah diunggah.',
                409,
                ErrorCode::DocumentAlreadyUploaded,
            );
        }

        /** @var string $disk */
        $disk = config('zentro.documents_disk', 'local');

        return DB::transaction(function () use ($user, $seller, $type, $file, $hash, $disk): SellerDocument {
            $path = $file->store("seller-documents/{$seller->getKey()}", $disk);

            if (! is_string($path)) {
                throw new ApiException('Berkas gagal disimpan.', 500, ErrorCode::ServerError);
            }

            $document = $seller->documents()->create([
                'type' => $type,
                'file_path' => $path,
                'file_hash' => $hash,
                'status' => SellerDocumentStatus::Pending,
            ]);

            if (in_array($seller->status, [SellerStatus::Pending, SellerStatus::Rejected], true)) {
                $seller->forceFill([
                    'status' => SellerStatus::UnderReview,
                    'submitted_at' => now(),
                    'rejection_reason' => null,
                    'rejected_at' => null,
                    'rejected_by' => null,
                ])->save();
            }

            AuditLog::record(
                action: 'seller.document_uploaded',
                entityType: 'seller_document',
                entityId: $document->getKey(),
                after: ['seller_id' => $seller->getKey(), 'type' => $type],
                actorId: $user->getKey(),
            );

            return $document;
        });
    }
}
