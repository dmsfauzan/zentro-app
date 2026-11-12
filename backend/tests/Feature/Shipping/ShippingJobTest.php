<?php

use App\Contracts\Shipping\ShippingProviderInterface;
use App\Enums\OrderStatus;
use App\Enums\SellerOrderStatus;
use App\Enums\ShipmentStatus;
use App\Infrastructure\Shipping\FakeShippingProvider;
use App\Jobs\AutoCompleteShipmentsJob;
use App\Jobs\SyncShipmentTrackingJob;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Support\OrderStatusAggregator;
use App\Modules\Shipping\Actions\UpdateShipmentStatusAction;
use App\Modules\Shipping\Models\Shipment;

beforeEach(function (): void {
    FakeShippingProvider::reset();
});

/**
 * @return array{0: Order, 1: Shipment}
 */
function deliveredShipmentForJobs(string $key): array
{
    [$buyer, $address] = buyerWithAddress();
    [$sellerUser, $seller] = makeSeller();
    $variant = publishedVariant(stock: 5, seller: $seller);

    $order = paidOrderFrom($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], $key);
    $sellerOrder = $order->sellerOrders()->firstOrFail();

    test()->postJson('/api/v1/seller/orders/'.$sellerOrder->getKey().'/confirm', [], authHeadersFor($sellerUser))->assertOk();

    $shipment = Shipment::query()->where('seller_order_id', $sellerOrder->getKey())->firstOrFail();

    test()->postJson('/api/v1/seller/shipments/'.$shipment->getKey().'/dispatch', ['tracking_number' => 'JOB-'.strtoupper($key)], authHeadersFor($sellerUser))->assertOk();

    postShippingWebhook(fakeShippingWebhookPayload($shipment, 'delivered', 'evt-'.$key))->assertOk();

    return [$order->refresh(), $shipment->refresh()];
}

it('auto-completes delivered sub-orders past the hold period', function (): void {
    [$order, $shipment] = deliveredShipmentForJobs('autocomplete-old');

    $sellerOrder = $order->sellerOrders()->firstOrFail();
    $sellerOrder->forceFill(['delivered_at' => now()->subDays(8)])->save();

    app()->make(AutoCompleteShipmentsJob::class)->handle(app(OrderStatusAggregator::class));

    expect($sellerOrder->refresh()->status)->toBe(SellerOrderStatus::Completed)
        ->and($order->refresh()->status)->toBe(OrderStatus::Completed);
});

it('does not auto-complete recent deliveries', function (): void {
    [$order, $shipment] = deliveredShipmentForJobs('autocomplete-recent');

    app()->make(AutoCompleteShipmentsJob::class)->handle(app(OrderStatusAggregator::class));

    expect($order->sellerOrders()->firstOrFail()->refresh()->status)->toBe(SellerOrderStatus::Delivered);
});

it('syncs tracking status from the provider job', function (): void {
    [, $shipment] = deliveredShipmentForJobs('sync-job');

    // Mundurkan status ke dispatched agar job punya sesuatu untuk diterapkan.
    $shipment->forceFill(['status' => ShipmentStatus::Dispatched, 'delivered_at' => null])->save();
    $sellerOrder = $shipment->sellerOrder()->firstOrFail();
    $sellerOrder->forceFill(['status' => SellerOrderStatus::Shipped, 'delivered_at' => null])->save();

    FakeShippingProvider::simulateProviderStatus($shipment->getKey(), ShipmentStatus::Delivered);

    app()->make(SyncShipmentTrackingJob::class)->handle(
        app(ShippingProviderInterface::class),
        app(UpdateShipmentStatusAction::class),
    );

    expect($shipment->refresh()->status)->toBe(ShipmentStatus::Delivered)
        ->and($sellerOrder->refresh()->status)->toBe(SellerOrderStatus::Delivered);
});

it('does nothing on sync when the provider reports the same status', function (): void {
    [, $shipment] = deliveredShipmentForJobs('sync-noop');

    $shipment->forceFill(['status' => ShipmentStatus::Dispatched, 'delivered_at' => null])->save();

    FakeShippingProvider::simulateProviderStatus($shipment->getKey(), ShipmentStatus::Dispatched);

    app()->make(SyncShipmentTrackingJob::class)->handle(
        app(ShippingProviderInterface::class),
        app(UpdateShipmentStatusAction::class),
    );

    expect($shipment->refresh()->status)->toBe(ShipmentStatus::Dispatched);
});
