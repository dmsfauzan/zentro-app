<?php

use App\Modules\Platform\Models\AuditLog;

it('lists orders for admins only', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 5);
    paidOrderFrom($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'admin-orders');

    $admin = makeAdminUser();

    $this->getJson('/api/v1/admin/orders', authHeadersFor($admin))
        ->assertOk()
        ->assertJsonPath('data.0.buyer.email', $buyer->email);

    $this->getJson('/api/v1/admin/orders', authHeadersFor($buyer))
        ->assertStatus(403);
});

it('lists payments for admins', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 5);
    paidOrderFrom($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'admin-payments');

    $admin = makeAdminUser();

    $this->getJson('/api/v1/admin/payments', authHeadersFor($admin))
        ->assertOk()
        ->assertJsonPath('data.0.status', 'paid');
});

it('lists audit logs for admins with permission', function (): void {
    $admin = makeAdminUser();
    AuditLog::record('test.action', 'order', 1, null, ['ok' => true], $admin->getKey());

    $this->getJson('/api/v1/admin/audit-logs', authHeadersFor($admin))
        ->assertOk()
        ->assertJsonPath('data.0.action', 'test.action');
});

it('lists commissions for the owning seller only', function (): void {
    [$buyer, $address] = buyerWithAddress();
    [$sellerUser, $seller] = makeSeller();
    $variant = publishedVariant(stock: 5, seller: $seller);

    paidOrderFrom($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'admin-commission');

    $this->getJson('/api/v1/seller/commissions', authHeadersFor($sellerUser))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'pending');

    [$otherUser] = makeSeller();

    $this->getJson('/api/v1/seller/commissions', authHeadersFor($otherUser))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
