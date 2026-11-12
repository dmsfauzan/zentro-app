<?php

namespace App\Modules\Seller\Http\Controllers\Admin;

use App\Modules\Seller\Models\SellerDocument;
use App\Support\Exceptions\ApiException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SellerDocumentController
{
    use AuthorizesRequests;

    /**
     * Mengunduh dokumen seller dari disk privat. Hanya untuk pengguna
     * dengan permission `manage_sellers` (dijaga middleware route + policy).
     */
    public function download(SellerDocument $document): StreamedResponse
    {
        $this->authorize('download', $document);

        /** @var string $disk */
        $disk = config('zentro.documents_disk', 'local');
        $storage = Storage::disk($disk);

        if (! $storage->exists($document->file_path)) {
            throw ApiException::notFound('Berkas dokumen tidak ditemukan.');
        }

        $extension = pathinfo($document->file_path, PATHINFO_EXTENSION) ?: 'bin';

        return $storage->download(
            $document->file_path,
            "seller-{$document->seller_id}-{$document->type->value}.{$extension}",
        );
    }
}
