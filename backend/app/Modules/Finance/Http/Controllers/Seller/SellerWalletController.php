<?php

namespace App\Modules\Finance\Http\Controllers\Seller;

use App\Modules\Finance\Http\Resources\WalletResource;
use App\Modules\Finance\Http\Resources\WalletTransactionResource;
use App\Modules\Finance\Models\SellerWallet;
use App\Modules\Finance\Models\WalletTransaction;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerWalletController
{
    public function show(Request $request): JsonResponse
    {
        $seller = $request->attributes->get('seller');

        $wallet = SellerWallet::query()->firstOrCreate(
            ['seller_id' => $seller->getKey()],
            ['pending_balance' => 0, 'available_balance' => 0, 'paid_balance' => 0],
        );

        return ApiResponse::success(
            (new WalletResource($wallet))->resolve(),
            'Saldo wallet berhasil diambil.',
        );
    }

    public function transactions(Request $request): JsonResponse
    {
        $seller = $request->attributes->get('seller');

        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage === 0 ? 20 : $perPage, 100));

        $transactions = WalletTransaction::query()
            ->where('seller_id', $seller->getKey())
            ->when($request->filled('bucket'), fn ($query) => $query->where('balance_bucket', $request->query('bucket')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->query('type')))
            ->latest('id')
            ->paginate($perPage);

        return ApiResponse::paginated(
            $transactions,
            fn (WalletTransaction $transaction): array => (new WalletTransactionResource($transaction))->resolve(),
            'Mutasi wallet berhasil diambil.',
        );
    }
}
