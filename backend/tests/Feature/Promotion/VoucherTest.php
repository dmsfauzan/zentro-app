<?php

use App\Enums\CommissionStatus;
use App\Enums\OrderStatus;
use App\Modules\Finance\Models\Commission;
use App\Modules\Order\Models\Order;
use App\Modules\Promotion\Models\VoucherUsage;

it('applies a platform percent voucher without reducing the seller commission base', function (): void {
    $voucher = makeVoucher();

    [$buyer, $address] = buyerWithAddress();
    [$sellerUser, $seller] = makeSeller();
    $variant = publishedVariant(stock: 5, price: 100_000, seller: $seller);

    $response = doCheckout(
        $buyer,
        $address,
        [['variant_id' => $variant->id, 'quantity' => 1]],
        'voucher-platform',
        [],
        $voucher->code,
    )->assertStatus(201);

    $response->assertJsonPath('data.discount_total', 10_000)
        ->assertJsonPath('data.grand_total', 105_000)
        ->assertJsonPath('data.voucher.code', 'SAVE10')
        ->assertJsonPath('data.voucher.discount', 10_000);

    $order = Order::query()->firstOrFail();
    $sellerOrder = $order->sellerOrders()->firstOrFail();

    expect((int) $order->voucher_id)->toBe($voucher->getKey())
        ->and((int) $sellerOrder->discount_total)->toBe(0)
        ->and((int) $sellerOrder->platform_funded_discount)->toBe(10_000)
        ->and((int) $sellerOrder->grand_total)->toBe(115_000);

    $this->assertDatabaseHas('voucher_usages', [
        'voucher_id' => $voucher->getKey(),
        'order_id' => $order->getKey(),
        'discount_amount' => 10_000,
    ]);

    expect($voucher->refresh()->used_count)->toBe(1);

    expect($order->refresh()->status)->toBe(OrderStatus::PendingPayment);

    postPaymentWebhook(fakeWebhookPayload($order, 'settlement', 'tx-voucher-platform'))->assertOk();

    // Komisi masih dihitung dari subtotal penuh (platform yang menanggung diskon).
    $commission = Commission::query()->where('seller_order_id', $sellerOrder->getKey())->firstOrFail();
    expect($commission->base_amount)->toBe(100_000)
        ->and($commission->commission_amount)->toBe(5000)
        ->and($commission->status)->toBe(CommissionStatus::Pending);
});

it('applies a seller funded voucher and reduces the commission base', function (): void {
    [$buyer, $address] = buyerWithAddress();
    [$sellerUser, $seller] = makeSeller();
    $variant = publishedVariant(stock: 5, price: 100_000, seller: $seller);

    $voucher = makeVoucher([
        'code' => 'TOKO15',
        'type' => 'fixed',
        'value' => 15_000,
        'scope' => 'seller',
        'seller_id' => $seller->getKey(),
        'funded_by' => 'seller',
    ]);

    doCheckout(
        $buyer,
        $address,
        [['variant_id' => $variant->id, 'quantity' => 1]],
        'voucher-seller',
        [],
        $voucher->code,
    )->assertStatus(201)
        ->assertJsonPath('data.discount_total', 15_000)
        ->assertJsonPath('data.grand_total', 100_000);

    $order = Order::query()->firstOrFail();
    $sellerOrder = $order->sellerOrders()->firstOrFail();

    expect((int) $sellerOrder->discount_total)->toBe(15_000)
        ->and((int) $sellerOrder->voucher_discount_borne_by_seller)->toBe(15_000)
        ->and((int) $sellerOrder->grand_total)->toBe(100_000);

    postPaymentWebhook(fakeWebhookPayload($order, 'settlement', 'tx-voucher-seller'))->assertOk();

    $commission = Commission::query()->where('seller_order_id', $sellerOrder->getKey())->firstOrFail();
    expect($commission->base_amount)->toBe(85_000)
        ->and($commission->commission_amount)->toBe(4250);
});

it('rejects a voucher below the minimum purchase', function (): void {
    $voucher = makeVoucher(['min_purchase' => 500_000]);

    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 5, price: 100_000);

    doCheckout($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'voucher-min', [], $voucher->code)
        ->assertStatus(422)
        ->assertJsonPath('errors.code', 'VOUCHER_NOT_AVAILABLE');
});

it('rejects an inactive or expired voucher', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 5, price: 100_000);

    $inactive = makeVoucher(['code' => 'OFF', 'is_active' => false]);
    doCheckout($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'voucher-off', [], $inactive->code)
        ->assertStatus(422)
        ->assertJsonPath('errors.code', 'VOUCHER_NOT_AVAILABLE');

    $expired = makeVoucher([
        'code' => 'OLD',
        'starts_at' => now()->subDays(10),
        'expires_at' => now()->subDay(),
    ]);
    doCheckout($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'voucher-old', [], $expired->code)
        ->assertStatus(422)
        ->assertJsonPath('errors.code', 'VOUCHER_NOT_AVAILABLE');
});

it('enforces the global usage limit', function (): void {
    $voucher = makeVoucher(['code' => 'ONCE', 'usage_limit' => 1]);

    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 10, price: 100_000);

    doCheckout($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'voucher-once-1', [], $voucher->code)
        ->assertStatus(201);

    [$otherBuyer, $otherAddress] = buyerWithAddress();

    doCheckout($otherBuyer, $otherAddress, [['variant_id' => $variant->id, 'quantity' => 1]], 'voucher-once-2', [], $voucher->code)
        ->assertStatus(422)
        ->assertJsonPath('errors.code', 'VOUCHER_NOT_AVAILABLE');

    expect(VoucherUsage::query()->count())->toBe(1);
});

it('enforces the per-user usage limit', function (): void {
    $voucher = makeVoucher(['code' => 'PERUSER', 'usage_limit_per_user' => 1]);

    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 10, price: 100_000);

    doCheckout($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'voucher-user-1', [], $voucher->code)
        ->assertStatus(201);

    doCheckout($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'voucher-user-2', [], $voucher->code)
        ->assertStatus(422)
        ->assertJsonPath('errors.code', 'VOUCHER_NOT_AVAILABLE');
});

it('previews a voucher discount without creating an order', function (): void {
    $voucher = makeVoucher(['code' => 'PREVIEW', 'type' => 'fixed', 'value' => 20_000]);

    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 5, price: 100_000);

    $this->postJson('/api/v1/checkout/preview', [
        'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
        'address_id' => $address->getKey(),
        'voucher_code' => $voucher->code,
    ], authHeadersFor($buyer))
        ->assertOk()
        ->assertJsonPath('data.summary.discount_total', 20_000)
        ->assertJsonPath('data.summary.grand_total', 95_000)
        ->assertJsonPath('data.voucher.available', true);
});

it('lists only the vouchers that apply to the cart in the preview', function (): void {
    [$buyer, $address] = buyerWithAddress();
    [, $seller] = makeSeller();
    $variant = publishedVariant(stock: 5, price: 100_000, seller: $seller);

    makeVoucher(['code' => 'OK10', 'type' => 'percent', 'value' => 1000]);
    makeVoucher([
        'code' => 'TOKO5',
        'type' => 'fixed',
        'value' => 5000,
        'scope' => 'seller',
        'seller_id' => $seller->getKey(),
        'funded_by' => 'seller',
    ]);

    // Tidak berlaku: min belanja terlalu tinggi.
    makeVoucher(['code' => 'MINIM', 'min_purchase' => 5_000_000]);

    // Tidak berlaku: voucher milik toko lain.
    [, $otherSeller] = makeSeller();
    makeVoucher([
        'code' => 'TOKOLAIN',
        'type' => 'fixed',
        'value' => 5000,
        'scope' => 'seller',
        'seller_id' => $otherSeller->getKey(),
        'funded_by' => 'seller',
    ]);

    // Tidak berlaku: nonaktif.
    makeVoucher(['code' => 'NONAKTIF', 'is_active' => false]);

    $response = $this->postJson('/api/v1/checkout/preview', [
        'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
        'address_id' => $address->getKey(),
    ], authHeadersFor($buyer))->assertOk();

    $codes = collect($response->json('data.available_vouchers'))->pluck('code')->all();

    expect($codes)->toBe(['OK10', 'TOKO5']);

    $response->assertJsonPath('data.available_vouchers.0.discount', 10_000)
        ->assertJsonPath('data.available_vouchers.1.discount', 5_000)
        ->assertJsonPath('data.voucher.available', false);
});
