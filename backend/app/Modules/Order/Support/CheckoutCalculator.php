<?php

namespace App\Modules\Order\Support;

use App\Contracts\Shipping\ShippingRateProvider;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Support\VariantAvailability;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Perhitungan checkout: validasi item, pengecekan stok, pengelompokan per
 * seller, dan perhitungan total. Dipakai oleh preview maupun checkout nyata
 * sehingga hasilnya konsisten.
 *
 * Harga, total, dan komisi TIDAK pernah diambil dari klien.
 */
final class CheckoutCalculator
{
    public function __construct(
        private readonly ShippingRateProvider $shipping,
    ) {}

    /**
     * Normalisasi item dari request: gabung duplikat, batasi jumlah & kuantitas.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, int> variant_id => quantity
     */
    public function normaliseItems(array $items): array
    {
        $maxItems = (int) config('zentro.order.max_items_per_checkout', 50);
        $maxQty = (int) config('zentro.order.max_quantity_per_item', 100);

        $map = [];

        foreach ($items as $item) {
            $variantId = (int) ($item['variant_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);

            if ($variantId <= 0 || $quantity <= 0) {
                throw new ApiException(
                    'Item checkout tidak valid.',
                    422,
                    ErrorCode::ValidationFailed,
                );
            }

            $map[$variantId] = ($map[$variantId] ?? 0) + $quantity;
        }

        if ($map === []) {
            throw new ApiException('Keranjang kosong.', 422, ErrorCode::CartEmpty);
        }

        if (count($map) > $maxItems) {
            throw new ApiException(
                "Maksimal {$maxItems} varian berbeda per checkout.",
                422,
                ErrorCode::ValidationFailed,
            );
        }

        foreach ($map as $variantId => $quantity) {
            if ($quantity > $maxQty) {
                throw new ApiException(
                    "Kuantitas per item maksimal {$maxQty}.",
                    422,
                    ErrorCode::ValidationFailed,
                    ['items' => ["variant {$variantId} melebihi batas kuantitas."]],
                );
            }
        }

        ksort($map);

        return $map;
    }

    /**
     * Memuat varian beserta relasi katalog. `$lock` mengaktifkan SELECT ... FOR UPDATE.
     *
     * @param  array<int, int>  $variantIds
     */
    public function loadVariants(array $variantIds, bool $lock = false): Collection
    {
        $query = ProductVariant::query()
            ->whereIn('id', $variantIds)
            ->with(['product.seller', 'inventory'])
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get()->keyBy('id');
    }

    /**
     * Validasi ketersediaan & bangun grup per seller.
     *
     * @param  Collection<int, ProductVariant>  $variants
     * @param  array<int, int>  $quantityMap  variant_id => quantity
     * @param  array<string, mixed>  $destination
     * @param  array<int, array<string, mixed>>  $selections
     * @return array{groups: array<int, array<string, mixed>>, summary: array<string, int>}
     */
    public function groupBySeller(
        Collection $variants,
        array $quantityMap,
        array $destination,
        array $selections = [],
    ): array {
        $groups = [];

        foreach ($quantityMap as $variantId => $quantity) {
            $variant = $variants->get($variantId);

            $this->assertPurchasable($variant, $quantity, $variantId);

            /** @var ProductVariant $variant */
            $product = $variant->product;
            $seller = $product->seller;
            $sellerId = (int) $seller->getKey();

            $groups[$sellerId] ??= [
                'seller' => $seller,
                'items' => [],
                'subtotal' => 0,
                'weight_gram' => 0.0,
            ];

            $lineTotal = (int) $variant->price * $quantity;

            $groups[$sellerId]['items'][] = [
                'variant' => $variant,
                'product' => $product,
                'quantity' => $quantity,
                'price' => (int) $variant->price,
                'line_total' => $lineTotal,
                'weight_gram' => $variant->weight_gram !== null ? (float) $variant->weight_gram : null,
            ];

            $groups[$sellerId]['subtotal'] += $lineTotal;
            $groups[$sellerId]['weight_gram'] += (float) ($variant->weight_gram ?? 0) * $quantity;
        }

        $summary = [
            'subtotal' => 0,
            'discount_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 0,
        ];

        $selectionMap = [];
        foreach ($selections as $selection) {
            if (isset($selection['seller_id'])) {
                $selectionMap[(int) $selection['seller_id']] = (string) ($selection['service_code'] ?? '');
            }
        }

        $result = [];

        foreach ($groups as $sellerId => $group) {
            $options = $this->shipping->quote($group['seller'], array_map(
                static fn (array $line): array => [
                    'variant_id' => (int) $line['variant']->getKey(),
                    'quantity' => $line['quantity'],
                    'weight_gram' => $line['weight_gram'],
                    'subtotal' => $line['line_total'],
                ],
                $group['items'],
            ), $destination);

            $selectedCode = $selectionMap[$sellerId] ?? ($options[0]['code'] ?? null);
            $selected = null;

            foreach ($options as $option) {
                if ($option['code'] === $selectedCode) {
                    $selected = $option;
                    break;
                }
            }

            if ($selected === null) {
                throw new ApiException(
                    'Pilihan pengiriman tidak tersedia untuk salah satu penjual.',
                    422,
                    ErrorCode::ValidationFailed,
                    ['shipping_selections' => ["penjual {$sellerId}: layanan tidak tersedia."]],
                );
            }

            $shippingCost = (int) $selected['cost'];
            $discount = 0;
            $groupTotal = $group['subtotal'] - $discount + $shippingCost;

            $summary['subtotal'] += $group['subtotal'];
            $summary['discount_total'] += $discount;
            $summary['shipping_total'] += $shippingCost;
            $summary['grand_total'] += $groupTotal;

            $result[] = [
                'seller_id' => $sellerId,
                'seller' => $group['seller'],
                'items' => $group['items'],
                'weight_gram' => round($group['weight_gram'], 2),
                'subtotal' => $group['subtotal'],
                'discount' => $discount,
                'shipping_options' => $options,
                'selected_shipping' => $selected,
                'shipping_cost' => $shippingCost,
                'grand_total' => $groupTotal,
            ];
        }

        return ['groups' => $result, 'summary' => $summary];
    }

    public function expiresAt(): Carbon
    {
        return now()->addHours((int) config('zentro.order.payment_timeout_hours', 24));
    }

    private function assertPurchasable(?ProductVariant $variant, int $quantity, int $variantId): void
    {
        VariantAvailability::assert($variant, $quantity);
    }
}
