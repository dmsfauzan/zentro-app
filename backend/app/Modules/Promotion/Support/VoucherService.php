<?php

namespace App\Modules\Promotion\Support;

use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use App\Modules\Identity\Models\User;
use App\Modules\Promotion\Models\Voucher;
use App\Modules\Promotion\Models\VoucherUsage;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;

/**
 * Validasi & alokasi voucher pada hasil perhitungan checkout.
 *
 * Aturan (B-3/B-6):
 * - Voucher platform ditanggung platform → TIDAK mengurangi basis komisi seller.
 * - Voucher seller ditanggung seller → mengurangi basis komisi seller.
 */
final class VoucherService
{
    /**
     * @param  array{groups: array<int, array<string, mixed>>, summary: array<string, int>}  $computed
     * @return array{
     *   computed: array{groups: array<int, array<string, mixed>>, summary: array<string, int>},
     *   voucher: Voucher|null,
     *   discount: int,
     *   seller_borne: array<int, int>,
     *   platform_funded: array<int, int>
     * }
     */
    public function apply(array $computed, User $buyer, ?string $code, bool $lock = false): array
    {
        $computed['voucher'] = [
            'code' => null,
            'name' => null,
            'type' => null,
            'scope' => null,
            'funded_by' => null,
            'discount' => 0,
            'available' => false,
        ];

        $code = $code !== null ? mb_strtoupper(trim($code)) : '';

        if ($code === '') {
            return $this->result($computed, null, 0, [], []);
        }

        $voucher = Voucher::query()
            ->where('code', $code)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->first();

        if (! $voucher instanceof Voucher) {
            throw new ApiException(
                'Kode voucher tidak dikenal.',
                422,
                ErrorCode::VoucherNotAvailable,
            );
        }

        $this->assertUsable($voucher, $buyer);

        $groups = $computed['groups'];
        $sellerIds = array_map(
            static fn (array $group): int => (int) $group['seller_id'],
            $groups,
        );

        $targets = $voucher->scope === VoucherScope::Seller
            ? [(int) $voucher->seller_id]
            : $sellerIds;

        $applicable = 0;

        foreach ($groups as $group) {
            if (in_array((int) $group['seller_id'], $targets, true)) {
                $applicable += (int) $group['subtotal'];
            }
        }

        if ($applicable <= 0) {
            throw new ApiException(
                'Voucher tidak berlaku untuk produk di keranjang ini.',
                422,
                ErrorCode::VoucherNotAvailable,
            );
        }

        if ($voucher->min_purchase > 0 && $applicable < $voucher->min_purchase) {
            throw new ApiException(
                'Minimal belanja untuk voucher ini adalah Rp '.number_format($voucher->min_purchase, 0, ',', '.').'.',
                422,
                ErrorCode::VoucherNotAvailable,
            );
        }

        $discount = $this->discount($voucher, $applicable);

        if ($discount <= 0) {
            return $this->result($computed, $voucher, 0, [], []);
        }

        if ($voucher->scope === VoucherScope::Seller) {
            $sellerBorne = [(int) $voucher->seller_id => $discount];
            $platformFunded = [];
        } else {
            $sellerBorne = [];
            $platformFunded = $this->allocatePlatform($discount, $groups, $applicable);
        }

        foreach ($computed['groups'] as &$group) {
            $sellerId = (int) $group['seller_id'];
            $borne = $sellerBorne[$sellerId] ?? 0;
            $platform = $platformFunded[$sellerId] ?? 0;

            $group['discount'] = $borne;
            $group['platform_discount'] = $platform;
            $group['grand_total'] = (int) $group['subtotal'] - $borne + (int) $group['shipping_cost'];
        }
        unset($group);

        $computed['summary']['discount_total'] = $discount;
        $computed['summary']['grand_total'] = array_sum(array_map(
            static fn (array $group): int => (int) $group['grand_total'],
            $computed['groups'],
        )) - array_sum($platformFunded);

        $computed['voucher'] = [
            'code' => $voucher->code,
            'name' => $voucher->name,
            'type' => $voucher->type->value,
            'scope' => $voucher->scope->value,
            'funded_by' => $voucher->funded_by->value,
            'discount' => $discount,
            'available' => true,
        ];

        return $this->result($computed, $voucher, $discount, $sellerBorne, $platformFunded);
    }

    /**
     * Daftar voucher yang benar-benar berlaku untuk isi keranjang saat ini.
     *
     * Dipakai halaman checkout untuk menampilkan pilihan diskon (checkbox).
     * Perhitungan memakai subtotal per seller yang sama dengan `apply()`,
     * sehingga diskon yang ditampilkan = diskon yang akan diterapkan server.
     *
     * @param  array{groups: array<int, array<string, mixed>>, summary?: array<string, int>}  $computed
     * @return array<int, array<string, mixed>>
     */
    public function availableFor(array $computed, User $buyer): array
    {
        $now = now();

        $vouchers = Voucher::query()
            ->where('is_active', true)
            ->where('starts_at', '<=', $now)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>=', $now))
            ->where(fn ($query) => $query->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'))
            ->orderBy('id')
            ->get();

        if ($vouchers->isEmpty()) {
            return [];
        }

        $groups = $computed['groups'];
        $sellerIds = array_map(
            static fn (array $group): int => (int) $group['seller_id'],
            $groups,
        );

        // Pemakaian per user dihitung sekali untuk semua kandidat.
        $usedByUser = VoucherUsage::query()
            ->where('user_id', $buyer->getKey())
            ->whereIn('voucher_id', $vouchers->modelKeys())
            ->selectRaw('voucher_id, COUNT(*) as total')
            ->groupBy('voucher_id')
            ->pluck('total', 'voucher_id');

        $available = [];

        foreach ($vouchers as $voucher) {
            if ($voucher->usage_limit_per_user !== null
                && (int) ($usedByUser[$voucher->getKey()] ?? 0) >= $voucher->usage_limit_per_user) {
                continue;
            }

            $targets = $voucher->scope === VoucherScope::Seller
                ? [(int) $voucher->seller_id]
                : $sellerIds;

            $applicable = 0;

            foreach ($groups as $group) {
                if (in_array((int) $group['seller_id'], $targets, true)) {
                    $applicable += (int) $group['subtotal'];
                }
            }

            if ($applicable <= 0) {
                continue;
            }

            if ($voucher->min_purchase > 0 && $applicable < $voucher->min_purchase) {
                continue;
            }

            $discount = $this->discount($voucher, $applicable);

            if ($discount <= 0) {
                continue;
            }

            $available[] = [
                'code' => $voucher->code,
                'name' => $voucher->name,
                'type' => $voucher->type->value,
                'scope' => $voucher->scope->value,
                'funded_by' => $voucher->funded_by->value,
                'min_purchase' => (int) $voucher->min_purchase,
                'discount' => $discount,
            ];
        }

        return $available;
    }

    private function assertUsable(Voucher $voucher, User $buyer): void
    {
        if (! $voucher->is_active) {
            throw $this->unavailable('Voucher sedang tidak aktif.');
        }

        if ($voucher->starts_at->isFuture()) {
            throw $this->unavailable('Voucher belum berlaku.');
        }

        if ($voucher->expires_at !== null && $voucher->expires_at->isPast()) {
            throw $this->unavailable('Voucher sudah kedaluwarsa.');
        }

        if ($voucher->usage_limit !== null && $voucher->used_count >= $voucher->usage_limit) {
            throw $this->unavailable('Kuota voucher sudah habis.');
        }

        if ($voucher->usage_limit_per_user !== null) {
            $used = VoucherUsage::query()
                ->where('voucher_id', $voucher->getKey())
                ->where('user_id', $buyer->getKey())
                ->count();

            if ($used >= $voucher->usage_limit_per_user) {
                throw $this->unavailable('Kamu sudah memakai voucher ini.');
            }
        }
    }

    private function discount(Voucher $voucher, int $subtotal): int
    {
        $value = $voucher->type === VoucherType::Percent
            ? intdiv($subtotal * $voucher->value, 10000)
            : $voucher->value;

        if ($voucher->type === VoucherType::Percent && $voucher->max_discount !== null) {
            $value = min($value, $voucher->max_discount);
        }

        return max(0, min($value, $subtotal));
    }

    /**
     * Sebar diskon platform secara proporsional agar laporan per seller akurat.
     *
     * @param  array<int, array<string, mixed>>  $groups
     * @return array<int, int> seller_id => diskon platform
     */
    private function allocatePlatform(int $discount, array $groups, int $applicable): array
    {
        $allocation = [];
        $remaining = $discount;
        $lastIndex = count($groups) - 1;

        foreach ($groups as $index => $group) {
            $subtotal = (int) $group['subtotal'];

            if ($subtotal <= 0) {
                continue;
            }

            $amount = $index === $lastIndex
                ? $remaining
                : (int) floor($discount * $subtotal / max(1, $applicable));

            $amount = max(0, min($amount, $subtotal, $remaining));

            if ($amount > 0) {
                $allocation[(int) $group['seller_id']] = $amount;
            }

            $remaining -= $amount;
        }

        return $allocation;
    }

    /**
     * @param  array<string, mixed>  $computed
     * @param  array<int, int>  $sellerBorne
     * @param  array<int, int>  $platformFunded
     * @return array{
     *   computed: array<string, mixed>,
     *   voucher: Voucher|null,
     *   discount: int,
     *   seller_borne: array<int, int>,
     *   platform_funded: array<int, int>
     * }
     */
    private function result(
        array $computed,
        ?Voucher $voucher,
        int $discount,
        array $sellerBorne,
        array $platformFunded,
    ): array {
        return [
            'computed' => $computed,
            'voucher' => $voucher,
            'discount' => $discount,
            'seller_borne' => $sellerBorne,
            'platform_funded' => $platformFunded,
        ];
    }

    private function unavailable(string $message): ApiException
    {
        return new ApiException($message, 422, ErrorCode::VoucherNotAvailable);
    }
}
