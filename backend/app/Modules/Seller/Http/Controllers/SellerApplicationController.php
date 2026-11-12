<?php

namespace App\Modules\Seller\Http\Controllers;

use App\Modules\Seller\Actions\SubmitSellerApplicationAction;
use App\Modules\Seller\Actions\UploadSellerDocumentAction;
use App\Modules\Seller\Http\Requests\StoreSellerApplicationRequest;
use App\Modules\Seller\Http\Requests\UploadSellerDocumentRequest;
use App\Modules\Seller\Http\Resources\SellerDocumentResource;
use App\Modules\Seller\Http\Resources\SellerResource;
use App\Modules\Seller\Models\Seller;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use App\Support\Http\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerApplicationController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly SubmitSellerApplicationAction $submit,
        private readonly UploadSellerDocumentAction $upload,
    ) {}

    public function store(StoreSellerApplicationRequest $request): JsonResponse
    {
        $seller = $this->submit->execute($request->user(), $request->validated());

        return ApiResponse::created(
            (new SellerResource($seller))->resolve(),
            'Aplikasi penjual berhasil dikirim.',
        );
    }

    public function show(Request $request): JsonResponse
    {
        $seller = $request->user()->seller()->with(['addresses', 'documents'])->first();

        if (! $seller instanceof Seller) {
            throw new ApiException(
                'Anda belum memiliki toko.',
                404,
                ErrorCode::SellerNotFound,
            );
        }

        return ApiResponse::success(
            (new SellerResource($seller))->resolve(),
            'Data aplikasi penjual berhasil diambil.',
        );
    }

    public function uploadDocument(UploadSellerDocumentRequest $request, Seller $seller): JsonResponse
    {
        $this->authorize('uploadDocument', $seller);

        $document = $this->upload->execute(
            $request->user(),
            $seller,
            $request->validated('type'),
            $request->file('file'),
        );

        return ApiResponse::created([
            'document' => (new SellerDocumentResource($document))->resolve(),
            'seller' => (new SellerResource($seller->fresh(['addresses', 'documents'])))->resolve(),
        ], 'Dokumen berhasil diunggah.');
    }
}
