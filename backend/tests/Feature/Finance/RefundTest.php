<?php

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Enums\CommissionStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\SellerOrderStatus;
use App\Jobs\RecoverStuckRefundsJob;
use App\Modules\Finance\Actions\ProcessPayoutAction;
use App\Modules\Finance\Actions\ProcessRefundAction;
use App\Modules\Finance\Actions\RequestPayoutAction;
use App\Modules\Finance\Models\Commission;
use App\Modules\Finance\Models\SellerWallet;
use App\Modules\Identity\Models\User;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\SellerOrder;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\Refund;
use App\Modules\Seller\Models\Seller;

/**
 * @return array{0: User, 1: User, 2: Seller, 3: Order, 4: SellerOrder, 5: Commission}
 */
function refundScenario(string $key, bool $settle = false, bool $paidOut = false): array
{
    [$buyer, $sellerUser, $seller, $order, $sellerOrder] = financeSellerScenario($key);
    $commission = Commission::query()->where('seller_order_id', $sellerOrder->getKey())->firstOrFail();

    if ($settle || $paidOut) {
        settleCommission($commission);
    }

    if ($paidOut) {
        $bank = sellerBankAccount($seller);
        $result = app(RequestPayoutAction::class)->execute($sellerUser, $seller, 'refund-po-'.$key, 110_000, $bank->getKey());
        app(ProcessPayoutAction::class)->execute($result['payout'], 'approve', null);
        app(ProcessPayoutAction::class)->execute($result['payout']->refresh(), 'mark_paid', null);
    }

    return [$buyer, $sellerUser, $seller, $order, $sellerOrder, $commission->refresh()];
}

it('creates a refund request as admin and forbids sellers', function (): void {
    [, $sellerUser, , $order, $sellerOrder] = refundScenario('refund-create');
    $admin = makeAdminUser();

    $this->postJson('/api/v1/admin/refunds', [
        'order_number' => $order->order_number,
        'seller_order_id' => $sellerOrder->getKey(),
        'amount' => (int) $sellerOrder->grand_total,
        'reason' => 'Barang rusak',
    ], authHeadersFor($admin))
        ->assertCreated()
        ->assertJsonPath('data.status', RefundStatus::Requested->value);

    $this->postJson('/api/v1/admin/refunds', [
        'order_number' => $order->order_number,
        'seller_order_id' => $sellerOrder->getKey(),
        'amount' => 1000,
        'reason' => 'Coba',
    ], authHeadersFor($sellerUser))->assertStatus(403);
});

it('rejects a refund above the sub-order total', function (): void {
    [, , , $order, $sellerOrder] = refundScenario('refund-invalid');
    $admin = makeAdminUser();

    $this->postJson('/api/v1/admin/refunds', [
        'order_number' => $order->order_number,
        'seller_order_id' => $sellerOrder->getKey(),
        'amount' => (int) $sellerOrder->grand_total + 1,
        'reason' => 'Terlalu besar',
    ], authHeadersFor($admin))
        ->assertStatus(422)
        ->assertJsonPath('errors.code', 'REFUND_AMOUNT_INVALID');
});

it('debits the pending bucket and reverses the commission before settlement', function (): void {
    [, , $seller, $order, $sellerOrder] = refundScenario('refund-pending');
    $admin = makeAdminUser();

    $refundId = $this->postJson('/api/v1/admin/refunds', [
        'order_number' => $order->order_number,
        'seller_order_id' => $sellerOrder->getKey(),
        'amount' => (int) $sellerOrder->grand_total,
        'reason' => 'Batal',
    ], authHeadersFor($admin))->json('data.id');

    $this->postJson("/api/v1/admin/refunds/{$refundId}/approve", [], authHeadersFor($admin))
        ->assertOk()
        ->assertJsonPath('data.status', RefundStatus::Succeeded->value);

    $wallet = SellerWallet::query()->where('seller_id', $seller->getKey())->firstOrFail();
    expect($wallet->pending_balance)->toBe(0)
        ->and($wallet->available_balance)->toBe(0);

    $commission = Commission::query()->where('seller_order_id', $sellerOrder->getKey())->firstOrFail();
    expect($commission->status)->toBe(CommissionStatus::Reversed);

    expect($sellerOrder->refresh()->status)->toBe(SellerOrderStatus::Refunded)
        ->and($order->refresh()->status->value)->toBe('refunded')
        ->and(Payment::query()->where('order_id', $order->getKey())->firstOrFail()->status)->toBe(PaymentStatus::Refunded);
});

it('debits the available bucket when the commission is already earned', function (): void {
    [, , $seller, $order, $sellerOrder] = refundScenario('refund-earned', settle: true);
    $admin = makeAdminUser();

    $refundId = $this->postJson('/api/v1/admin/refunds', [
        'order_number' => $order->order_number,
        'seller_order_id' => $sellerOrder->getKey(),
        'amount' => (int) $sellerOrder->grand_total,
        'reason' => 'Batal',
    ], authHeadersFor($admin))->json('data.id');

    $this->postJson("/api/v1/admin/refunds/{$refundId}/approve", [], authHeadersFor($admin))->assertOk();

    $wallet = SellerWallet::query()->where('seller_id', $seller->getKey())->firstOrFail();
    expect($wallet->available_balance)->toBe(0)
        ->and($wallet->pending_balance)->toBe(0);
});

it('records the shortfall as an explicit debt after a payout', function (): void {
    [, , $seller, $order, $sellerOrder] = refundScenario('refund-paidout', paidOut: true);
    $admin = makeAdminUser();

    $refundId = $this->postJson('/api/v1/admin/refunds', [
        'order_number' => $order->order_number,
        'seller_order_id' => $sellerOrder->getKey(),
        'amount' => (int) $sellerOrder->grand_total,
        'reason' => 'Retur setelah cair',
    ], authHeadersFor($admin))->json('data.id');

    $this->postJson("/api/v1/admin/refunds/{$refundId}/approve", [], authHeadersFor($admin))->assertOk();

    $wallet = SellerWallet::query()->where('seller_id', $seller->getKey())->firstOrFail();
    // `available` tidak lagi negatif; kekurangan dicatat sebagai utang eksplisit.
    expect($wallet->available_balance)->toBe(0)
        ->and($wallet->debt_balance)->toBe(110_000)
        ->and($wallet->paid_balance)->toBe(110_000);
});

it('marks the payment partially refunded when only one sub-order is refunded', function (): void {
    [$buyer, $address] = buyerWithAddress();
    [, $sellerA] = makeSeller();
    [, $sellerB] = makeSeller();
    $variantA = publishedVariant(stock: 5, seller: $sellerA);
    $variantB = publishedVariant(stock: 5, seller: $sellerB);

    $order = paidOrderFrom($buyer, $address, [
        ['variant_id' => $variantA->id, 'quantity' => 1],
        ['variant_id' => $variantB->id, 'quantity' => 1],
    ], 'refund-partial');

    $sellerOrderA = $order->sellerOrders()->where('seller_id', $sellerA->getKey())->firstOrFail();
    $admin = makeAdminUser();

    $refundId = $this->postJson('/api/v1/admin/refunds', [
        'order_number' => $order->order_number,
        'seller_order_id' => $sellerOrderA->getKey(),
        'amount' => (int) $sellerOrderA->grand_total,
        'reason' => 'Satu sub-order',
    ], authHeadersFor($admin))->json('data.id');

    $this->postJson("/api/v1/admin/refunds/{$refundId}/approve", [], authHeadersFor($admin))->assertOk();

    expect(Payment::query()->where('order_id', $order->getKey())->firstOrFail()->status)
        ->toBe(PaymentStatus::PartiallyRefunded)
        // Campuran status terminal → parent dibiarkan.
        ->and($order->refresh()->status->value)->toBe('paid');
});

it('rejects a refund with a reason and requires it', function (): void {
    [, , , $order, $sellerOrder] = refundScenario('refund-reject');
    $admin = makeAdminUser();

    $refundId = $this->postJson('/api/v1/admin/refunds', [
        'order_number' => $order->order_number,
        'seller_order_id' => $sellerOrder->getKey(),
        'amount' => 10_000,
        'reason' => 'Ragu',
    ], authHeadersFor($admin))->json('data.id');

    $this->postJson("/api/v1/admin/refunds/{$refundId}/reject", [], authHeadersFor($admin))
        ->assertStatus(422)
        ->assertJsonPath('errors.code', 'VALIDATION_FAILED');

    $this->postJson("/api/v1/admin/refunds/{$refundId}/reject", ['reason' => 'Tidak valid'], authHeadersFor($admin))
        ->assertOk()
        ->assertJsonPath('data.status', RefundStatus::Rejected->value)
        ->assertJsonPath('data.rejection_reason', 'Tidak valid');
});

it('refuses a second active refund for the same sub-order', function (): void {
    [, , , $order, $sellerOrder] = refundScenario('refund-duplicate');
    $admin = makeAdminUser();

    $payload = [
        'order_number' => $order->order_number,
        'seller_order_id' => $sellerOrder->getKey(),
        'amount' => 10_000,
        'reason' => 'Pertama',
    ];

    $this->postJson('/api/v1/admin/refunds', $payload, authHeadersFor($admin))->assertCreated();
    $this->postJson('/api/v1/admin/refunds', $payload, authHeadersFor($admin))
        ->assertStatus(409)
        ->assertJsonPath('errors.code', 'REFUND_NOT_PROCESSABLE');

    expect(Refund::query()->count())->toBe(1);
});

it('debits the seller proportionally for a partial product refund', function (): void {
    [, , $seller, $order, $sellerOrder] = refundScenario('refund-partial');
    $admin = makeAdminUser();

    $refundId = $this->postJson('/api/v1/admin/refunds', [
        'order_number' => $order->order_number,
        'seller_order_id' => $sellerOrder->getKey(),
        'amount' => 10_000,
        'product_amount' => 10_000,
        'shipping_amount' => 0,
        'reason' => 'Kurang 1 item',
    ], authHeadersFor($admin))->assertCreated()->json('data.id');

    $this->postJson("/api/v1/admin/refunds/{$refundId}/approve", [], authHeadersFor($admin))
        ->assertOk()
        ->assertJsonPath('data.status', RefundStatus::Succeeded->value)
        ->assertJsonPath('data.seller_debit_amount', 9_500)
        ->assertJsonPath('data.commission_reversal_amount', 500);

    // seller_net 110.000 − debit proporsional 9.500 = 100.500.
    $wallet = SellerWallet::query()->where('seller_id', $seller->getKey())->firstOrFail();
    expect($wallet->pending_balance)->toBe(100_500);

    // Refund parsial: komisi belum direversal sampai produk habis.
    $commission = Commission::query()->where('seller_order_id', $sellerOrder->getKey())->firstOrFail();
    expect($commission->status)->toBe(CommissionStatus::Pending);

    $sellerOrder->refresh();
    expect($sellerOrder->refunded_product_amount)->toBe(10_000)
        ->and($sellerOrder->seller_refunded_amount)->toBe(9_500)
        ->and($sellerOrder->commission_reversed_amount)->toBe(500)
        ->and($sellerOrder->status)->toBe(SellerOrderStatus::Completed);
});

it('refuses a refund beyond the remaining refundable amount', function (): void {
    [, , , $order, $sellerOrder] = refundScenario('refund-over');
    $admin = makeAdminUser();

    $first = $this->postJson('/api/v1/admin/refunds', [
        'order_number' => $order->order_number,
        'seller_order_id' => $sellerOrder->getKey(),
        'amount' => 10_000,
        'product_amount' => 10_000,
        'shipping_amount' => 0,
        'reason' => 'Pertama',
    ], authHeadersFor($admin))->assertCreated()->json('data.id');
    $this->postJson("/api/v1/admin/refunds/{$first}/approve", [], authHeadersFor($admin))->assertOk();

    // Sisa: produk 90.000 + ongkir 15.000 = 105.000.
    $this->postJson('/api/v1/admin/refunds', [
        'order_number' => $order->order_number,
        'seller_order_id' => $sellerOrder->getKey(),
        'amount' => 200_000,
        'reason' => 'Kedua',
    ], authHeadersFor($admin))
        ->assertStatus(422)
        ->assertJsonPath('errors.code', 'REFUND_AMOUNT_INVALID');
});

it('retries finalize without calling the gateway again', function (): void {
    [, , $seller, $order, $sellerOrder] = refundScenario('refund-finalize-retry');
    $admin = makeAdminUser();

    $spy = Mockery::spy(PaymentGatewayInterface::class);
    app()->instance(PaymentGatewayInterface::class, $spy);

    $refundId = $this->postJson('/api/v1/admin/refunds', [
        'order_number' => $order->order_number,
        'seller_order_id' => $sellerOrder->getKey(),
        'amount' => (int) $sellerOrder->grand_total,
        'reason' => 'Retur',
    ], authHeadersFor($admin))->assertCreated()->json('data.id');

    // Simulasikan gateway sudah sukses (provider_reference tersimpan) tetapi finalize gagal.
    Refund::query()->whereKey($refundId)->update([
        'status' => RefundStatus::Processing->value,
        'provider_reference' => 'MID-REF-1',
        'provider_refund_key' => 'RFD-KEY-1',
    ]);

    $this->postJson("/api/v1/admin/refunds/{$refundId}/approve", [], authHeadersFor($admin))
        ->assertOk()
        ->assertJsonPath('data.status', RefundStatus::Succeeded->value);

    $spy->shouldNotHaveReceived('refund');

    $wallet = SellerWallet::query()->where('seller_id', $seller->getKey())->firstOrFail();
    expect($wallet->pending_balance)->toBe(0);
});

it('settles only the unrefunded remainder after a partial refund', function (): void {
    [, , $seller, $order, $sellerOrder] = refundScenario('refund-settle-remainder');
    $admin = makeAdminUser();

    $refundId = $this->postJson('/api/v1/admin/refunds', [
        'order_number' => $order->order_number,
        'seller_order_id' => $sellerOrder->getKey(),
        'amount' => 10_000,
        'product_amount' => 10_000,
        'shipping_amount' => 0,
        'reason' => 'Parsial',
    ], authHeadersFor($admin))->assertCreated()->json('data.id');
    $this->postJson("/api/v1/admin/refunds/{$refundId}/approve", [], authHeadersFor($admin))->assertOk();

    $commission = Commission::query()->where('seller_order_id', $sellerOrder->getKey())->firstOrFail();
    settleCommission($commission);

    $wallet = SellerWallet::query()->where('seller_id', $seller->getKey())->firstOrFail();
    expect($wallet->pending_balance)->toBe(0)
        ->and($wallet->available_balance)->toBe(100_500);
});

it('recovers a stuck processing refund without calling the gateway', function (): void {
    [, , $seller, $order, $sellerOrder] = refundScenario('refund-recover');
    $admin = makeAdminUser();

    $spy = Mockery::spy(PaymentGatewayInterface::class);
    app()->instance(PaymentGatewayInterface::class, $spy);

    $refundId = $this->postJson('/api/v1/admin/refunds', [
        'order_number' => $order->order_number,
        'seller_order_id' => $sellerOrder->getKey(),
        'amount' => (int) $sellerOrder->grand_total,
        'reason' => 'Retur',
    ], authHeadersFor($admin))->assertCreated()->json('data.id');

    Refund::query()->whereKey($refundId)->update([
        'status' => RefundStatus::Processing->value,
        'provider_reference' => 'MID-RECOVER-1',
        'provider_refund_key' => 'RFD-KEY-R',
    ]);

    app()->make(RecoverStuckRefundsJob::class)->handle(app(ProcessRefundAction::class));

    $spy->shouldNotHaveReceived('refund');

    expect(Refund::query()->findOrFail($refundId)->status)->toBe(RefundStatus::Succeeded);

    $wallet = SellerWallet::query()->where('seller_id', $seller->getKey())->firstOrFail();
    expect($wallet->pending_balance)->toBe(0);
});
