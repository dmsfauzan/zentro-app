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
 * @return array{0: User, 1: User, 2: User, 3: Order, 4: Shipment, 5: Shipment}
 */
function deliveredTwoSellerOrder(string $key): array
{
    [$buyer, $address] = buyerWithAddress();
    [$sellerUserA, $sellerA] = makeSeller();
    [$sellerUserB, $sellerB] = makeSeller();
    $variantA = publishedVariant(stock: 5, seller: $sellerA);
    $variantB = publishedVariant(stock: 5, seller: $sellerB);

    $order = paidOrderFrom($buyer, $address, [
        ['variant_id' => $variantA->id, 'quantity' => 1],
        ['variant_id' => $variantB->id, 'quantity' => 1],
    ], $key);

    $shipmentA = Shipment::query()->where('seller_id', $sellerA->getKey())->firstOrFail();
    $shipmentB = Shipment::query()->where('seller_id', $sellerB->getKey())->firstOrFail();

    foreach ([[$sellerUserA, $shipmentA, 'R-A'], [$sellerUserB, $shipmentB, 'R-B']] as [$sellerUser, $shipment, $tracking]) {
        $sellerOrder = $order->sellerOrders()->where('seller_id', $shipment->seller_id)->firstOrFail();

        test()->postJson('/api/v1/seller/orders/'.$sellerOrder->getKey().'/confirm', [], authHeadersFor($sellerUser))->assertOk();
        test()->postJson('/api/v1/seller/shipments/'.$shipment->getKey().'/dispatch', ['tracking_number' => $tracking], authHeadersFor($sellerUser))->assertOk();
        postShippingWebhook(fakeShippingWebhookPayload($shipment, 'delivered', 'evt-'.$tracking))->assertOk();
    }

    return [$buyer, $sellerUserA, $sellerUserB, $order->refresh(), $shipmentA, $shipmentB];
}

it('completes only the requested delivered sub-order', function (): void {
    [$buyer, , , $order, $shipmentA] = deliveredTwoSellerOrder('recv-single');

    $sellerOrderA = $order->sellerOrders()->where('seller_id', $shipmentA->seller_id)->firstOrFail();

    $this->postJson(
        '/api/v1/orders/'.$order->order_number.'/confirm-received',
        ['seller_order_id' => $sellerOrderA->getKey()],
        authHeadersFor($buyer),
    )->assertOk();

    expect($sellerOrderA->refresh()->status)->toBe(SellerOrderStatus::Completed)
        ->and($order->sellerOrders()->where('status', SellerOrderStatus::Delivered->value)->count())->toBe(1);
});

it('refuses confirm-received while nothing is delivered yet', function (): void {
    [$buyer, $address] = buyerWithAddress();
    [$sellerUser, $seller] = makeSeller();
    $variant = publishedVariant(stock: 5, seller: $seller);

    $order = paidOrderFrom($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'recv-not-delivered');

    $this->postJson(
        '/api/v1/orders/'.$order->order_number.'/confirm-received',
        [],
        authHeadersFor($buyer),
    )
        ->assertStatus(409)
        ->assertJsonPath('errors.code', 'ORDER_NOT_CONFIRMABLE');
});

it('forbids a non-owner from confirming receipt', function (): void {
    [, , , $order] = deliveredTwoSellerOrder('recv-owner');
    $stranger = User::factory()->create();

    $this->postJson(
        '/api/v1/orders/'.$order->order_number.'/confirm-received',
        [],
        authHeadersFor($stranger),
    )->assertStatus(403);
});

it('is idempotent when receipt is confirmed twice', function (): void {
    [$buyer, , , $order] = deliveredTwoSellerOrder('recv-idem');

    $this->postJson('/api/v1/orders/'.$order->order_number.'/confirm-received', [], authHeadersFor($buyer))->assertOk();
    $this->postJson('/api/v1/orders/'.$order->order_number.'/confirm-received', [], authHeadersFor($buyer))->assertOk();

    expect($order->refresh()->status)->toBe(OrderStatus::Completed);
});
