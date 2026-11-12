<?php

namespace App\Modules\Shipping\Actions;

use App\Contracts\Shipping\ShippingProviderInterface;
use App\Enums\ShipmentStatus;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderAddress;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Order\Models\SellerOrder;
use App\Modules\Shipping\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Membuat satu shipment (`pending`) untuk sebuah sub-order (idempotent).
 *
 * Dipanggil otomatis saat pembayaran sukses. Tidak memanggil jaringan di dalam
 * transaksi: pembuatan di provider dilakukan setelah baris shipment tersimpan.
 */
final class CreateShipmentAction
{
    public function __construct(
        private readonly ShippingProviderInterface $provider,
    ) {}

    public function ensure(SellerOrder $sellerOrder): Shipment
    {
        $existing = Shipment::query()
            ->where('seller_order_id', $sellerOrder->getKey())
            ->first();

        if ($existing instanceof Shipment) {
            return $existing;
        }

        $shipment = DB::transaction(function () use ($sellerOrder): Shipment {
            /** @var SellerOrder $locked */
            $locked = SellerOrder::query()
                ->whereKey($sellerOrder->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $existing = Shipment::query()
                ->where('seller_order_id', $locked->getKey())
                ->first();

            if ($existing instanceof Shipment) {
                return $existing;
            }

            $order = Order::query()->find($locked->order_id);
            $destination = OrderAddress::query()
                ->where('order_id', $locked->order_id)
                ->where('type', 'shipping')
                ->first();

            $origin = $locked->seller?->originAddress;

            $weight = (float) OrderItem::query()
                ->where('seller_order_id', $locked->getKey())
                ->get()
                ->sum(fn (OrderItem $item): float => (float) ($item->weight_gram_snapshot ?? 0) * (int) $item->quantity);

            $shipment = Shipment::query()->create([
                'seller_order_id' => $locked->getKey(),
                'seller_id' => $locked->seller_id,
                'provider' => $this->provider->name(),
                'status' => ShipmentStatus::Pending,
                'shipping_cost' => (int) $locked->shipping_cost,
                'weight_gram' => round($weight, 2),
                'origin_city' => $origin?->city,
                'destination_city' => $destination?->city,
            ]);

            $shipment->histories()->create([
                'status' => ShipmentStatus::Pending,
                'description' => 'Shipment dibuat',
                'occurred_at' => now(),
            ]);

            return $shipment;
        });

        if ($shipment->provider_shipment_id === null) {
            try {
                $creation = $this->provider->createShipment($shipment);
                $shipment->forceFill([
                    'provider' => $this->provider->name(),
                    'provider_shipment_id' => $creation->providerShipmentId,
                    'label_path' => $creation->labelPath,
                ])->save();
            } catch (Throwable $e) {
                // Shipment tetap ada; seller masih dapat mengirim secara manual.
                report($e);
            }
        }

        return $shipment->refresh();
    }
}
