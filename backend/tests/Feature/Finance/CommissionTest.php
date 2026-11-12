<?php

use App\Enums\CommissionStatus;
use App\Enums\WalletTransactionType;
use App\Modules\Catalog\Models\Category;
use App\Modules\Finance\Models\Commission;
use App\Modules\Finance\Models\CommissionRule;
use App\Modules\Finance\Models\SellerWallet;
use App\Modules\Finance\Models\WalletTransaction;

it('creates a commission and pending balance when payment succeeds', function (): void {
    [, , $seller, $order, $sellerOrder] = financeSellerScenario('comm-create');

    $commission = Commission::query()->where('seller_order_id', $sellerOrder->getKey())->firstOrFail();

    // subtotal 100000, ongkir 15000 → grand_total 115000
    expect($commission->base_amount)->toBe(100_000)
        ->and($commission->rate_bp)->toBe(500)
        ->and($commission->commission_amount)->toBe(5000)
        ->and($commission->status)->toBe(CommissionStatus::Pending);

    $sellerOrder->refresh();
    expect($sellerOrder->commission_amount)->toBe(5000)
        ->and($sellerOrder->seller_net_amount)->toBe(110_000);

    $wallet = SellerWallet::query()->where('seller_id', $seller->getKey())->firstOrFail();
    expect($wallet->pending_balance)->toBe(110_000)
        ->and($wallet->available_balance)->toBe(0);

    $this->assertDatabaseHas('wallet_transactions', [
        'seller_id' => $seller->getKey(),
        'type' => WalletTransactionType::SalePending->value,
        'balance_bucket' => 'pending',
        'amount' => 110_000,
        'balance_after' => 110_000,
    ]);
});

it('does not duplicate commission on a replayed payment webhook', function (): void {
    [, , $seller, $order, $sellerOrder] = financeSellerScenario('comm-replay');

    postPaymentWebhook(fakeWebhookPayload($order, 'settlement', 'tx-comm-replay'))->assertOk();

    expect(Commission::query()->count())->toBe(1)
        ->and(WalletTransaction::query()
            ->where('type', WalletTransactionType::SalePending->value)
            ->count())->toBe(1);

    $wallet = SellerWallet::query()->where('seller_id', $seller->getKey())->firstOrFail();
    expect($wallet->pending_balance)->toBe(110_000);
});

it('excludes shipping from the commission basis', function (): void {
    [, , , , $sellerOrder] = financeSellerScenario('comm-shipping');

    $commission = Commission::query()->where('seller_order_id', $sellerOrder->getKey())->firstOrFail();

    // Basis = subtotal (100000), bukan grand_total (115000).
    expect($commission->base_amount)->toBe(100_000);
});

it('prefers a seller rule over the global rule', function (): void {
    [$buyer, $address] = buyerWithAddress();
    [$sellerUser, $seller] = makeSeller();
    $variant = publishedVariant(stock: 5, price: 100_000, seller: $seller);

    CommissionRule::query()->create([
        'name' => 'Global 5%',
        'scope' => 'global',
        'rate_bp' => 500,
        'effective_from' => now()->subDay()->toDateString(),
    ]);

    CommissionRule::query()->create([
        'name' => 'Seller 2%',
        'scope' => 'seller',
        'seller_id' => $seller->getKey(),
        'rate_bp' => 200,
        'effective_from' => now()->subDay()->toDateString(),
    ]);

    $order = paidOrderFrom($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'comm-rule');
    $commission = Commission::query()->where('seller_order_id', $order->sellerOrders()->firstOrFail()->getKey())->firstOrFail();

    expect($commission->rate_bp)->toBe(200)
        ->and($commission->commission_amount)->toBe(2000);
});

it('applies a category rule when the sub-order has a single category', function (): void {
    [$buyer, $address] = buyerWithAddress();
    [$sellerUser, $seller] = makeSeller();

    // publishedVariant membuat produk tanpa kategori eksplisit; set kategori khusus.
    $variant = publishedVariant(stock: 5, price: 100_000, seller: $seller);
    $category = Category::factory()->create();
    $variant->product()->update(['category_id' => $category->getKey()]);

    CommissionRule::query()->create([
        'name' => 'Global 5%',
        'scope' => 'global',
        'rate_bp' => 500,
        'effective_from' => now()->subDay()->toDateString(),
    ]);

    CommissionRule::query()->create([
        'name' => 'Category 1%',
        'scope' => 'category',
        'category_id' => $category->getKey(),
        'rate_bp' => 100,
        'effective_from' => now()->subDay()->toDateString(),
    ]);

    $order = paidOrderFrom($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'comm-category');
    $commission = Commission::query()->where('seller_order_id', $order->sellerOrders()->firstOrFail()->getKey())->firstOrFail();

    expect($commission->rate_bp)->toBe(100);
});
