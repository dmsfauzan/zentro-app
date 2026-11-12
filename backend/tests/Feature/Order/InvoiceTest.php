<?php

use App\Modules\Identity\Models\User;

it('returns an invoice for a paid order', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 5, price: 100_000);

    $order = paidOrderFrom($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'invoice-paid');

    $response = $this->getJson("/api/v1/orders/{$order->order_number}/invoice", authHeadersFor($buyer))
        ->assertOk()
        ->assertJsonPath('data.order_number', $order->order_number)
        ->assertJsonPath('data.summary.subtotal', 100_000)
        ->assertJsonPath('data.summary.grand_total', 115_000)
        ->assertJsonPath('data.payment.status', 'paid')
        ->assertJsonPath('data.buyer.email', $buyer->email);

    expect($response->json('data.invoice_number'))
        ->toBe('INV/'.$order->paid_at->format('Ymd').'/'.$order->order_number);

    $response->assertJsonCount(1, 'data.groups')
        ->assertJsonPath('data.groups.0.items.0.quantity', 1)
        ->assertJsonPath('data.groups.0.items.0.product_name', $variant->product->name);
});

it('refuses an invoice before the order is paid', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 5, price: 100_000);

    doCheckout($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'invoice-unpaid')
        ->assertStatus(201);

    $order = $buyer->orders()->latest('id')->firstOrFail();

    $this->getJson("/api/v1/orders/{$order->order_number}/invoice", authHeadersFor($buyer))
        ->assertStatus(409)
        ->assertJsonPath('errors.code', 'INVOICE_NOT_AVAILABLE');
});

it('forbids a stranger from viewing the invoice', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 5, price: 100_000);

    $order = paidOrderFrom($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'invoice-owner');

    $stranger = User::factory()->create();

    $this->getJson("/api/v1/orders/{$order->order_number}/invoice", authHeadersFor($stranger))
        ->assertStatus(403);
});

it('allows an admin to view the invoice', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 5, price: 100_000);

    $order = paidOrderFrom($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'invoice-admin');

    $admin = makeAdminUser();

    $this->getJson("/api/v1/orders/{$order->order_number}/invoice", authHeadersFor($admin))
        ->assertOk()
        ->assertJsonPath('data.order_number', $order->order_number);
});
