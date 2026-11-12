<?php

use App\Modules\Identity\Models\User;
use App\Modules\Notification\Models\Notification;
use App\Modules\Shipping\Actions\ConfirmSellerOrderAction;
use App\Modules\Shipping\Actions\DispatchShipmentAction;
use Illuminate\Support\Str;

it('notifies buyer and seller when payment succeeds', function (): void {
    [$buyer, $address] = buyerWithAddress();
    [$sellerUser, $seller] = makeSeller();
    $variant = publishedVariant(stock: 5, seller: $seller);

    $order = paidOrderFrom($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'notif-paid');

    expect(Notification::query()->where('user_id', $buyer->getKey())->where('type', 'order.paid')->count())->toBe(1)
        ->and(Notification::query()->where('user_id', $sellerUser->getKey())->where('type', 'seller.order.paid')->count())->toBe(1);
});

it('lists notifications and marks them read', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 5);

    paidOrderFrom($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'notif-list');

    $response = $this->getJson('/api/v1/notifications', authHeadersFor($buyer))
        ->assertOk()
        ->assertJsonPath('data.unread_count', 1);

    $id = $response->json('data.items.0.id');

    $this->postJson("/api/v1/notifications/{$id}/read", [], authHeadersFor($buyer))
        ->assertOk();

    $this->getJson('/api/v1/notifications', authHeadersFor($buyer))
        ->assertOk()
        ->assertJsonPath('data.unread_count', 0);

    $this->postJson('/api/v1/notifications/read-all', [], authHeadersFor($buyer))
        ->assertOk();
});

it('shows a notification detail to its owner only', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 5);
    paidOrderFrom($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'notif-detail');

    $notification = Notification::query()->where('user_id', $buyer->getKey())->firstOrFail();

    $this->getJson("/api/v1/notifications/{$notification->id}", authHeadersFor($buyer))
        ->assertOk()
        ->assertJsonPath('data.id', $notification->id)
        ->assertJsonPath('data.type', 'order.paid')
        ->assertJsonPath('data.title', $notification->title);

    $stranger = User::factory()->create();

    $this->getJson("/api/v1/notifications/{$notification->id}", authHeadersFor($stranger))
        ->assertStatus(403);

    $this->getJson('/api/v1/notifications/'.Str::uuid(), authHeadersFor($buyer))
        ->assertStatus(404);
});

it('forbids reading another user notification', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 5);
    paidOrderFrom($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'notif-owner');

    $notification = Notification::query()->where('user_id', $buyer->getKey())->firstOrFail();
    $stranger = User::factory()->create();

    $this->postJson("/api/v1/notifications/{$notification->id}/read", [], authHeadersFor($stranger))
        ->assertStatus(403);
});

it('notifies the buyer when a shipment is delivered', function (): void {
    [$buyer, $address] = buyerWithAddress();
    [$sellerUser, $seller] = makeSeller();
    $variant = publishedVariant(stock: 5, seller: $seller);

    $order = paidOrderFrom($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'notif-delivered');
    $sellerOrder = $order->sellerOrders()->firstOrFail();

    app(ConfirmSellerOrderAction::class)->execute($sellerOrder, $sellerUser->getKey());
    $shipment = $sellerOrder->shipment()->firstOrFail();
    app(DispatchShipmentAction::class)->execute($shipment, ['tracking_number' => 'NT-1'], $sellerUser->getKey());

    postShippingWebhook(fakeShippingWebhookPayload($shipment->refresh(), 'delivered', 'evt-notif-delivered'))->assertOk();

    expect(Notification::query()
        ->where('user_id', $buyer->getKey())
        ->where('type', 'shipment.delivered')
        ->count())->toBe(1);
});
