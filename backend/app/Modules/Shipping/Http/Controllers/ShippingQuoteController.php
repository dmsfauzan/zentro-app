<?php

namespace App\Modules\Shipping\Http\Controllers;

use App\Contracts\Shipping\ShippingRateProvider;
use App\Modules\Seller\Models\Seller;
use App\Modules\Shipping\Http\Requests\ShippingQuoteRequest;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Throwable;

class ShippingQuoteController
{
    public function __construct(
        private readonly ShippingRateProvider $rates,
    ) {}

    public function index(ShippingQuoteRequest $request): JsonResponse
    {
        $seller = Seller::query()
            ->whereKey((int) $request->validated('seller_id'))
            ->first();

        if (! $seller instanceof Seller) {
            throw new ApiException('Penjual tidak ditemukan.', 404, ErrorCode::NotFound);
        }

        $items = [[
            'variant_id' => 0,
            'quantity' => 1,
            'weight_gram' => (float) $request->validated('weight_gram'),
            'subtotal' => (int) ($request->validated('item_value') ?? 0),
        ]];

        try {
            $options = $this->rates->quote($seller, $items, [
                'city' => $request->validated('destination_city'),
                'province' => $request->validated('destination_province'),
                'postal_code' => $request->validated('destination_postal_code'),
            ]);
        } catch (Throwable $e) {
            report($e);

            throw new ApiException(
                'Opsi pengiriman tidak tersedia saat ini.',
                503,
                ErrorCode::ShippingQuoteUnavailable,
            );
        }

        return ApiResponse::success($options, 'Opsi pengiriman berhasil dihitung.');
    }
}
