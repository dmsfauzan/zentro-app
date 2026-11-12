<?php

namespace App\Modules\Order\Actions;

use App\Enums\InventoryMovementType;
use App\Enums\InventoryReservationStatus;
use App\Enums\OrderStatus;
use App\Enums\SellerOrderStatus;
use App\Enums\VoucherScope;
use App\Modules\Cart\Models\CartItem;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Support\CatalogCache;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserAddress;
use App\Modules\Inventory\Models\Inventory;
use App\Modules\Inventory\Models\InventoryReservation;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderAddress;
use App\Modules\Order\Models\OrderStatusHistory;
use App\Modules\Order\Models\SellerOrderStatusHistory;
use App\Modules\Order\Support\CheckoutCalculator;
use App\Modules\Promotion\Models\Voucher;
use App\Modules\Promotion\Models\VoucherUsage;
use App\Modules\Promotion\Support\VoucherService;
use App\Support\Idempotency\IdempotencyService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Checkout: satu order induk + N sub-order (per seller), dengan reservasi stok
 * atomik dan idempotency.
 *
 * Catatan penting:
 * - Baris `inventories` dikunci (SELECT ... FOR UPDATE) supaya tidak overselling.
 * - Tidak ada panggilan jaringan eksternal di dalam transaksi.
 * - Harga/total/komisi tidak pernah diambil dari klien.
 */
final class PlaceCheckoutAction
{
    private const MAX_ORDER_NUMBER_ATTEMPTS = 5;

    public function __construct(
        private readonly CheckoutCalculator $calculator,
        private readonly IdempotencyService $idempotency,
        private readonly VoucherService $vouchers,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, array<string, mixed>>  $selections
     * @return array{replayed: bool, payload: array<string, mixed>, status: int}
     */
    public function execute(
        User $buyer,
        string $idempotencyKey,
        array $items,
        UserAddress $address,
        array $selections = [],
        ?string $voucherCode = null,
    ): array {
        $voucherCode = $voucherCode !== null ? mb_strtoupper(trim($voucherCode)) : null;

        $payloadForHash = [
            'items' => $items,
            'address_id' => $address->getKey(),
            'shipping_selections' => $selections,
            'voucher_code' => $voucherCode ?: null,
        ];

        $guard = $this->idempotency->begin($idempotencyKey, $buyer->getKey(), 'checkout', $payloadForHash);

        if (($guard['replay'] ?? false) === true) {
            return [
                'replayed' => true,
                'payload' => $guard['snapshot'] ?? [],
                'status' => $guard['status'] ?? 200,
            ];
        }

        /** @var string $hash */
        $hash = $guard['hash'];

        $quantityMap = $this->calculator->normaliseItems($items);
        $destination = $this->destinationFrom($address);

        $attempt = 0;

        while (true) {
            try {
                $payload = $this->idempotency->transactional(function () use (
                    $buyer,
                    $idempotencyKey,
                    $hash,
                    $quantityMap,
                    $destination,
                    $selections,
                    $address,
                    $voucherCode,
                ): array {
                    return $this->place($buyer, $idempotencyKey, $hash, $quantityMap, $destination, $selections, $address, $voucherCode);
                });

                return ['replayed' => false, 'payload' => $payload, 'status' => 201];
            } catch (QueryException $e) {
                if ($this->idempotency->isDuplicateKeyError($e) && str_contains($e->getMessage(), 'idempotency_keys')) {
                    $replay = $this->idempotency->replayOrFail($idempotencyKey, $hash);

                    return [
                        'replayed' => true,
                        'payload' => $replay['snapshot'] ?? [],
                        'status' => $replay['status'] ?? 200,
                    ];
                }

                if ($this->isOrderNumberCollision($e) && ++$attempt < self::MAX_ORDER_NUMBER_ATTEMPTS) {
                    continue;
                }

                throw $e;
            }
        }
    }

    /**
     * @param  array<int, int>  $quantityMap
     * @param  array<string, mixed>  $destination
     * @param  array<int, array<string, mixed>>  $selections
     * @return array<string, mixed>
     */
    private function place(
        User $buyer,
        string $idempotencyKey,
        string $hash,
        array $quantityMap,
        array $destination,
        array $selections,
        UserAddress $address,
        ?string $voucherCode = null,
    ): array {
        $variants = $this->calculator->loadVariants(array_keys($quantityMap));

        /** @var Collection<int, Inventory> $inventories */
        $inventories = Inventory::query()
            ->whereIn('variant_id', array_keys($quantityMap))
            ->orderBy('variant_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('variant_id');

        foreach ($variants as $variant) {
            $variant->setRelation('inventory', $inventories->get($variant->getKey()));
        }

        $computed = $this->calculator->groupBySeller($variants, $quantityMap, $destination, $selections);

        $voucherResult = $this->vouchers->apply($computed, $buyer, $voucherCode, lock: true);

        $computed = $voucherResult['computed'];
        /** @var Voucher|null $voucher */
        $voucher = $voucherResult['voucher'];

        $expiresAt = $this->calculator->expiresAt();
        $orderNumber = $this->nextOrderNumber();
        $summary = $computed['summary'];

        $order = Order::query()->create([
            'order_number' => $orderNumber,
            'buyer_id' => $buyer->getKey(),
            'voucher_id' => $voucher?->getKey(),
            'status' => OrderStatus::PendingPayment,
            'subtotal' => $summary['subtotal'],
            'discount_total' => $summary['discount_total'],
            'shipping_total' => $summary['shipping_total'],
            'grand_total' => $summary['grand_total'],
            'currency' => 'IDR',
            'expires_at' => $expiresAt,
        ]);

        OrderStatusHistory::query()->create([
            'order_id' => $order->getKey(),
            'from_status' => null,
            'to_status' => OrderStatus::PendingPayment->value,
            'changed_by' => $buyer->getKey(),
            'reason' => 'Checkout dibuat',
        ]);

        $sellerOrderPayloads = [];

        foreach ($computed['groups'] as $group) {
            $sellerOrder = $order->sellerOrders()->create([
                'seller_id' => $group['seller_id'],
                'seller_order_number' => $orderNumber.'-S'.$group['seller_id'],
                'status' => SellerOrderStatus::Pending,
                'subtotal' => $group['subtotal'],
                'discount_total' => $group['discount'],
                'shipping_cost' => $group['shipping_cost'],
                'grand_total' => $group['grand_total'],
                'commission_base_amount' => 0,
                'commission_rate_bp' => 0,
                'commission_amount' => 0,
                'seller_net_amount' => 0,
                'voucher_discount_borne_by_seller' => $group['discount'],
                'platform_funded_discount' => $group['platform_discount'] ?? 0,
            ]);

            SellerOrderStatusHistory::query()->create([
                'seller_order_id' => $sellerOrder->getKey(),
                'from_status' => null,
                'to_status' => SellerOrderStatus::Pending->value,
                'changed_by' => $buyer->getKey(),
                'reason' => 'Menunggu pembayaran',
            ]);

            foreach ($group['items'] as $line) {
                /** @var ProductVariant $variant */
                $variant = $line['variant'];
                /** @var Inventory $inventory */
                $inventory = $inventories->get($variant->getKey());

                $orderItem = $order->items()->create([
                    'seller_order_id' => $sellerOrder->getKey(),
                    'seller_id' => $group['seller_id'],
                    'product_id' => $variant->product_id,
                    'variant_id' => $variant->getKey(),
                    'product_name_snapshot' => $line['product']->name,
                    'sku_snapshot' => $variant->sku,
                    'variant_name_snapshot' => $variant->variant_name,
                    'price_snapshot' => $line['price'],
                    'quantity' => $line['quantity'],
                    'discount_amount' => 0,
                    'line_total' => $line['line_total'],
                    'weight_gram_snapshot' => $line['weight_gram'],
                ]);

                $this->reserve($inventory, $variant->getKey(), $order->getKey(), $orderItem->getKey(), $line['quantity'], $expiresAt, $buyer->getKey());
            }

            $sellerOrderPayloads[] = [
                'id' => $sellerOrder->getKey(),
                'seller_id' => $group['seller_id'],
                'seller_order_number' => $sellerOrder->seller_order_number,
                'status' => SellerOrderStatus::Pending->value,
                'subtotal' => $group['subtotal'],
                'shipping_cost' => $group['shipping_cost'],
                'discount_total' => $group['discount'],
                'grand_total' => $group['grand_total'],
            ];
        }

        $this->snapshotAddress($order->getKey(), $address);

        if ($voucher instanceof Voucher) {
            $sellerOrderId = $voucher->scope === VoucherScope::Seller
                ? $order->sellerOrders()->where('seller_id', $voucher->seller_id)->value('id')
                : null;

            VoucherUsage::query()->create([
                'voucher_id' => $voucher->getKey(),
                'user_id' => $buyer->getKey(),
                'order_id' => $order->getKey(),
                'seller_order_id' => $sellerOrderId,
                'discount_amount' => $voucherResult['discount'],
            ]);

            $voucher->used_count = (int) $voucher->used_count + 1;
            $voucher->save();
        }

        $this->clearCartItems($buyer->getKey(), array_keys($quantityMap));

        CatalogCache::bump();

        $payload = [
            'order_number' => $order->order_number,
            'status' => OrderStatus::PendingPayment->value,
            'subtotal' => $summary['subtotal'],
            'discount_total' => $summary['discount_total'],
            'shipping_total' => $summary['shipping_total'],
            'grand_total' => $summary['grand_total'],
            'currency' => 'IDR',
            'expires_at' => $expiresAt->toIso8601String(),
            'seller_orders' => $sellerOrderPayloads,
            'voucher' => $voucher instanceof Voucher
                ? [
                    'code' => $voucher->code,
                    'discount' => $voucherResult['discount'],
                ]
                : null,
            // Pembayaran dibuat pada Phase 5.
            'payment' => null,
        ];

        $this->idempotency->complete(
            $idempotencyKey,
            $buyer->getKey(),
            'checkout',
            $hash,
            $payload,
            201,
        );

        return $payload;
    }

    private function reserve(
        Inventory $inventory,
        int $variantId,
        int $orderId,
        int $orderItemId,
        int $quantity,
        Carbon $expiresAt,
        int $actorId,
    ): void {
        $reservedBefore = $inventory->reserved_stock;
        $reservedAfter = $reservedBefore + $quantity;

        $inventory->reserved_stock = $reservedAfter;
        $inventory->save();

        InventoryReservation::query()->create([
            'inventory_id' => $inventory->getKey(),
            'variant_id' => $variantId,
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'quantity' => $quantity,
            'status' => InventoryReservationStatus::Active,
            'expires_at' => $expiresAt,
        ]);

        $inventory->movements()->create([
            'variant_id' => $variantId,
            'type' => InventoryMovementType::Reservation,
            'quantity' => $quantity,
            'stock_before' => $inventory->stock,
            'stock_after' => $inventory->stock,
            'reserved_before' => $reservedBefore,
            'reserved_after' => $reservedAfter,
            'reference_type' => 'order',
            'reference_id' => $orderId,
            'actor_id' => $actorId,
            'note' => 'Reservasi stok checkout',
        ]);
    }

    private function snapshotAddress(int $orderId, UserAddress $address): void
    {
        OrderAddress::query()->create([
            'order_id' => $orderId,
            'seller_order_id' => null,
            'type' => 'shipping',
            'recipient_name' => $address->recipient_name,
            'phone' => $address->phone,
            'address_line' => $address->address_line,
            'province' => $address->province,
            'city' => $address->city,
            'district' => $address->district,
            'village' => $address->village,
            'postal_code' => $address->postal_code,
            'latitude' => $address->latitude,
            'longitude' => $address->longitude,
        ]);
    }

    private function nextOrderNumber(): string
    {
        $prefix = 'ZTR-'.now()->format('Ymd').'-';
        $count = Order::query()->where('order_number', 'like', $prefix.'%')->count() + 1;

        return $prefix.str_pad((string) $count, 6, '0', STR_PAD_LEFT);
    }

    private function isOrderNumberCollision(QueryException $e): bool
    {
        return str_contains($e->getMessage(), 'orders_order_number_unique');
    }

    /**
     * @return array<string, mixed>
     */
    private function destinationFrom(UserAddress $address): array
    {
        return [
            'city' => $address->city,
            'province' => $address->province,
            'postal_code' => $address->postal_code,
        ];
    }

    private function clearCartItems(int $userId, array $variantIds): void
    {
        CartItem::query()
            ->whereHas('cart', fn ($query) => $query->where('user_id', $userId))
            ->whereIn('variant_id', $variantIds)
            ->delete();
    }
}
