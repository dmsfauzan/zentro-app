<?php

namespace App\Modules\Finance\Http\Controllers\Seller;

use App\Modules\Finance\Http\Requests\BankAccountRequest;
use App\Modules\Finance\Models\SellerBankAccount;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SellerBankAccountController
{
    public function index(Request $request): JsonResponse
    {
        $seller = $request->attributes->get('seller');

        $accounts = SellerBankAccount::query()
            ->where('seller_id', $seller->getKey())
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->map(fn (SellerBankAccount $account): array => [
                'id' => $account->id,
                'bank_name' => $account->bank_name,
                'account_number' => $account->maskedNumber(),
                'account_name' => $account->account_name,
                'is_default' => $account->is_default,
            ])
            ->all();

        return ApiResponse::success($accounts, 'Daftar rekening berhasil diambil.');
    }

    public function store(BankAccountRequest $request): JsonResponse
    {
        $seller = $request->attributes->get('seller');

        $account = DB::transaction(function () use ($request, $seller): SellerBankAccount {
            $isDefault = (bool) $request->boolean('is_default')
                || SellerBankAccount::query()->where('seller_id', $seller->getKey())->count() === 0;

            if ($isDefault) {
                SellerBankAccount::query()
                    ->where('seller_id', $seller->getKey())
                    ->update(['is_default' => false]);
            }

            return SellerBankAccount::query()->create([
                'seller_id' => $seller->getKey(),
                'bank_name' => (string) $request->validated('bank_name'),
                'account_number' => (string) $request->validated('account_number'),
                'account_name' => (string) $request->validated('account_name'),
                'is_default' => $isDefault,
            ]);
        });

        return ApiResponse::success([
            'id' => $account->id,
            'bank_name' => $account->bank_name,
            'account_number' => $account->maskedNumber(),
            'account_name' => $account->account_name,
            'is_default' => $account->is_default,
        ], 'Rekening berhasil ditambahkan.', 201);
    }
}
