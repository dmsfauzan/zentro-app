<?php

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Models\CommissionRule;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Order\Models\SellerOrder;

/**
 * Memilih rule komisi paling spesifik: seller > kategori > global.
 *
 * Rule kategori hanya berlaku bila seluruh item sub-order berada di satu
 * kategori yang sama (penyederhanaan MVP — lihat docs/18).
 */
final class CommissionRuleResolver
{
    public function resolve(SellerOrder $sellerOrder): ?CommissionRule
    {
        $categoryId = $this->singleCategoryId($sellerOrder);
        $today = now()->toDateString();

        $rules = CommissionRule::query()
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $today)
            ->where(function ($query) use ($today): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $today);
            })
            ->where(function ($query) use ($sellerOrder, $categoryId): void {
                $query->where(function ($q) use ($sellerOrder): void {
                    $q->where('scope', 'seller')->where('seller_id', $sellerOrder->seller_id);
                })->orWhere(function ($q) use ($categoryId): void {
                    $q->where('scope', 'global')->whereNull('seller_id')->whereNull('category_id');

                    if ($categoryId !== null) {
                        $q->orWhere(function ($inner) use ($categoryId): void {
                            $inner->where('scope', 'category')->where('category_id', $categoryId);
                        });
                    }
                });
            })
            ->get();

        return $rules
            ->sortByDesc(fn (CommissionRule $rule): array => [
                $this->specificity($rule),
                $rule->priority,
                $rule->effective_from?->timestamp ?? 0,
            ])
            ->first();
    }

    private function specificity(CommissionRule $rule): int
    {
        return match ($rule->scope) {
            'seller' => 3,
            'category' => 2,
            default => 1,
        };
    }

    private function singleCategoryId(SellerOrder $sellerOrder): ?int
    {
        $categoryIds = OrderItem::query()
            ->where('seller_order_id', $sellerOrder->getKey())
            ->with('product:id,category_id')
            ->get()
            ->map(fn (OrderItem $item): ?int => $item->product?->category_id)
            ->filter()
            ->unique()
            ->values();

        return $categoryIds->count() === 1 ? (int) $categoryIds->first() : null;
    }
}
