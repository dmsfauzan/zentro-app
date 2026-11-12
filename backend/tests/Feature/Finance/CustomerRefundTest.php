<?php

use App\Modules\Identity\Models\User;

it('lets a buyer request a refund for their own paid order', function (): void {
    [$buyer, , , $order] = financeSellerScenario('customer-refund');
    $sellerOrder = $order->sellerOrders()->firstOrFail();

    $this->postJson("/api/v1/orders/{$order->order_number}/refunds", [
        'seller_order_id' => $sellerOrder->id,
        'reason' => 'Barang tidak sesuai deskripsi',
    ], authHeadersFor($buyer))
        ->assertStatus(201)
        ->assertJsonPath('data.status', 'requested')
        ->assertJsonPath('data.seller_order_id', $sellerOrder->id)
        ->assertJsonPath('data.amount', (int) $sellerOrder->grand_total);

    $this->getJson("/api/v1/orders/{$order->order_number}/refunds", authHeadersFor($buyer))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('forbids another buyer from requesting a refund', function (): void {
    [, , , $order] = financeSellerScenario('customer-refund-stranger');
    $sellerOrder = $order->sellerOrders()->firstOrFail();
    $stranger = User::factory()->create();

    $this->postJson("/api/v1/orders/{$order->order_number}/refunds", [
        'seller_order_id' => $sellerOrder->id,
        'reason' => 'Mencoba akses',
    ], authHeadersFor($stranger))->assertStatus(403);
});

it('refuses a duplicate active refund for the same sub-order', function (): void {
    [$buyer, , , $order] = financeSellerScenario('customer-refund-dup');
    $sellerOrder = $order->sellerOrders()->firstOrFail();

    $payload = [
        'seller_order_id' => $sellerOrder->id,
        'reason' => 'Barang rusak',
    ];

    $this->postJson("/api/v1/orders/{$order->order_number}/refunds", $payload, authHeadersFor($buyer))
        ->assertStatus(201);

    $this->postJson("/api/v1/orders/{$order->order_number}/refunds", $payload, authHeadersFor($buyer))
        ->assertStatus(409)
        ->assertJsonPath('errors.code', 'REFUND_NOT_PROCESSABLE');
});
