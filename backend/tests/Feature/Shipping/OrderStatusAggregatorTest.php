<?php

use App\Enums\OrderStatus;
use App\Enums\SellerOrderStatus;
use App\Infrastructure\Shipping\FakeShippingProvider;
use App\Modules\Identity\Models\User;
use App\Modules\Order\Models\Order;
use App\Modules\Shipping\Models\Shipment;

beforeEach(function (): void {
    FakeShippingProvider::reset();
});

/**
 * Order paid dengan dua seller + dua shipment.
 *
 * @return array{0: User, 1: User, 2: User, 3: Order, 4: Shipment, 5: Shipment}
 */
function twoSellerPaidOrder(string $key): array
{
    [$buyer, $address] = buyerWithAddress();
    [$sellerUserA, $sellerA] = makeSeller();
    [$sellerUserB, $sellerB] = makeSeller();
    $variantA = publishedVariant(stock: 5, price: 50_000, seller: $sellerA);
    $variantB = publishedVariant(stock: 5, price: 70_000, seller: $sellerB);

    $order = paidOrderFrom($buyer, $address, [
        ['variant_id' => $variantA->id, 'quantity' => 1],
        ['variant_id' => $variantB->id, 'quantity' => 1],
    ], $key);

    $shipmentA = Shipment::query()->where('seller_id', $sellerA->getKey())->firstOrFail();
    $shipmentB = Shipment::query()->where('seller_id', $sellerB->getKey())->firstOrFail();

    return [$buyer, $sellerUserA, $sellerUserB, $order, $shipmentA, $shipmentB];
}

it('keeps the order paid while every sub-order is still pending', function (): void {
    [, , , $order] = twoSellerPaidOrder('agg-paid');

    expect($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->sellerOrders()->where('status', SellerOrderStatus::Pending->value)->count())->toBe(2);
});

it('moves the order through processing, partially shipped and shipped', function (): void {
    [, $sellerUserA, $sellerUserB, $order, $shipmentA, $shipmentB] = twoSellerPaidOrder('agg-flow');

    $sellerOrderA = $order->sellerOrders()->where('seller_id', $shipmentA->seller_id)->firstOrFail();
    $sellerOrderB = $order->sellerOrders()->where('seller_id', $shipmentB->seller_id)->firstOrFail();

    $this->postJson('/api/v1/seller/orders/'.$sellerOrderA->getKey().'/confirm', [], authHeadersFor($sellerUserA))->assertOk();
    expect($order->refresh()->status)->toBe(OrderStatus::Processing);

    $this->postJson('/api/v1/seller/shipments/'.$shipmentA->getKey().'/dispatch', ['tracking_number' => 'AGG-A'], authHeadersFor($sellerUserA))->assertOk();
    expect($order->refresh()->status)->toBe(OrderStatus::PartiallyShipped);

    $this->postJson('/api/v1/seller/orders/'.$sellerOrderB->getKey().'/confirm', [], authHeadersFor($sellerUserB))->assertOk();
    $this->postJson('/api/v1/seller/shipments/'.$shipmentB->getKey().'/dispatch', ['tracking_number' => 'AGG-B'], authHeadersFor($sellerUserB))->assertOk();
    expect($order->refresh()->status)->toBe(OrderStatus::Shipped);

    postShippingWebhook(fakeShippingWebhookPayload($shipmentA, 'delivered', 'evt-agg-a'))->assertOk();
    expect($order->refresh()->status)->toBe(OrderStatus::Shipped);

    postShippingWebhook(fakeShippingWebhookPayload($shipmentB, 'delivered', 'evt-agg-b'))->assertOk();
    expect($order->refresh()->status)->toBe(OrderStatus::Shipped)
        ->and($order->sellerOrders()->where('status', SellerOrderStatus::Delivered->value)->count())->toBe(2);
});

it('completes the order when every sub-order is completed', function (): void {
    [$buyer, $sellerUserA, $sellerUserB, $order, $shipmentA, $shipmentB] = twoSellerPaidOrder('agg-complete');

    foreach ([[$sellerUserA, $shipmentA, 'C-A'], [$sellerUserB, $shipmentB, 'C-B']] as [$sellerUser, $shipment, $tracking]) {
        $sellerOrder = $order->sellerOrders()->where('seller_id', $shipment->seller_id)->firstOrFail();

        $this->postJson('/api/v1/seller/orders/'.$sellerOrder->getKey().'/confirm', [], authHeadersFor($sellerUser))->assertOk();
        $this->postJson('/api/v1/seller/shipments/'.$shipment->getKey().'/dispatch', ['tracking_number' => $tracking], authHeadersFor($sellerUser))->assertOk();
        postShippingWebhook(fakeShippingWebhookPayload($shipment, 'delivered', 'evt-'.$tracking))->assertOk();
    }

    $this->postJson(
        '/api/v1/orders/'.$order->order_number.'/confirm-received',
        [],
        authHeadersFor($buyer),
    )->assertOk()->assertJsonPath('data.status', OrderStatus::Completed->value);

    expect($order->refresh()->status)->toBe(OrderStatus::Completed)
        ->and($order->sellerOrders()->where('status', SellerOrderStatus::Completed->value)->count())->toBe(2);
});

it('writes an order status history entry for every aggregation change', function (): void {
    [, $sellerUserA, , $order, $shipmentA] = twoSellerPaidOrder('agg-history');

    $sellerOrder = $order->sellerOrders()->where('seller_id', $shipmentA->seller_id)->firstOrFail();
    $this->postJson('/api/v1/seller/orders/'.$sellerOrder->getKey().'/confirm', [], authHeadersFor($sellerUserA))->assertOk();

    $this->assertDatabaseHas('order_status_histories', [
        'order_id' => $order->getKey(),
        'from_status' => OrderStatus::Paid->value,
        'to_status' => OrderStatus::Processing->value,
        'reason' => 'Agregasi status sub-order',
    ]);
});
