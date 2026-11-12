<?php

namespace App\Modules\Seller\Http\Controllers;

use App\Enums\SellerOrderStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Inventory;
use App\Modules\Order\Models\SellerOrder;
use App\Modules\Seller\Http\Resources\SellerResource;
use App\Modules\Seller\Models\Seller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class SellerDashboardController
{
    public function show(): JsonResponse
    {
        /** @var Seller $seller */
        $seller = request()->attributes->get('seller');
        $seller->load(['addresses', 'documents']);

        $pendingStatuses = [
            SellerOrderStatus::Pending->value,
            SellerOrderStatus::Confirmed->value,
            SellerOrderStatus::Processing->value,
        ];

        $stats = [
            'products' => Product::query()->where('seller_id', $seller->getKey())->count(),
            'pending_orders' => SellerOrder::query()
                ->where('seller_id', $seller->getKey())
                ->whereIn('status', $pendingStatuses)
                ->count(),
            'orders_today' => SellerOrder::query()
                ->where('seller_id', $seller->getKey())
                ->whereDate('created_at', now()->toDateString())
                ->count(),
            // Proxy pendapatan: sub-order yang sudah selesai (wallet = Phase 7).
            'revenue_this_month' => (int) SellerOrder::query()
                ->where('seller_id', $seller->getKey())
                ->where('status', SellerOrderStatus::Completed->value)
                ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('grand_total'),
            'low_stock_variants' => Inventory::query()
                ->whereHas('variant.product', fn ($query) => $query->where('seller_id', $seller->getKey()))
                ->whereColumn('stock', '<=', 'low_stock_threshold')
                ->count(),
            'wallet' => [
                'pending' => (int) ($seller->wallet?->pending_balance ?? 0),
                'available' => (int) ($seller->wallet?->available_balance ?? 0),
                'paid' => (int) ($seller->wallet?->paid_balance ?? 0),
            ],
        ];

        return ApiResponse::success([
            'store' => (new SellerResource($seller))->resolve(),
            'stats' => $stats,
            'stats_available' => true,
        ], 'Ringkasan dashboard berhasil diambil.');
    }
}
