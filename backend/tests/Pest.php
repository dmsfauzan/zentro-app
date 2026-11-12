<?php

use App\Infrastructure\Payment\FakePaymentGateway;
use App\Infrastructure\Shipping\ManualShippingProvider;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Finance\Actions\ReleaseSettlementAction;
use App\Modules\Finance\Models\Commission;
use App\Modules\Finance\Models\SellerBankAccount;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserAddress;
use App\Modules\Inventory\Models\Inventory;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\SellerOrder;
use App\Modules\Promotion\Models\Voucher;
use App\Modules\Seller\Models\Seller;
use App\Modules\Shipping\Actions\ConfirmReceivedAction;
use App\Modules\Shipping\Actions\ConfirmSellerOrderAction;
use App\Modules\Shipping\Actions\DispatchShipmentAction;
use App\Modules\Shipping\Models\Shipment;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)
    ->beforeEach(function (): void {
        $this->seed(RolePermissionSeeder::class);
        // Cegah test menulis ke public/uploads (gunakan disk palsu).
        Storage::fake('product_images');
    })
    ->in('Feature');

uses(TestCase::class)->in('Unit');

/**
 * Membangun token "fake" (base64url dari JSON klaim) untuk
 * FakeFirebaseTokenVerifier. Base64url dipakai karena bearer token header
 * tidak boleh mengandung koma (perilaku `bearerToken()` Laravel).
 *
 * @param  array<string, mixed>  $claims
 */
function fakeToken(array $claims = []): string
{
    $payload = array_merge([
        'uid' => 'user-1',
        'email' => 'user1@zentro.test',
        'name' => 'User One',
        'email_verified' => true,
        'exp' => time() + 3600,
    ], $claims);

    $json = json_encode($payload, JSON_THROW_ON_ERROR);

    return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
}

/**
 * Header Authorization berisi token fake.
 *
 * @param  array<string, mixed>  $claims
 * @return array<string, string>
 */
function fakeAuthHeaders(array $claims = []): array
{
    return ['Authorization' => 'Bearer '.fakeToken($claims)];
}

/**
 * Header Authorization untuk user tertentu (uid & email dari user asli).
 *
 * @return array<string, string>
 */
function authHeadersFor(User $user): array
{
    return fakeAuthHeaders([
        'uid' => $user->firebase_uid,
        'email' => $user->email,
        'name' => $user->name,
    ]);
}

/**
 * Payload aplikasi seller.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function sellerPayload(array $overrides = []): array
{
    return array_merge([
        'store_name' => 'Toko Zentro',
        'description' => 'Menjual berbagai kebutuhan sehari-hari.',
        'address' => [
            'contact_name' => 'Budi',
            'phone' => '+628123456789',
            'address_line' => 'Jl. Merdeka No. 1',
            'province' => 'DKI Jakarta',
            'city' => 'Jakarta Pusat',
            'district' => 'Menteng',
            'village' => 'Menteng',
            'postal_code' => '10310',
        ],
    ], $overrides);
}

/**
 * File gambar fixture nyata (menghindari ketergantungan ekstensi GD).
 */
function fixtureImage(string $name = 'product-400.png'): UploadedFile
{
    $path = base_path('tests/Fixtures/'.$name);

    return new UploadedFile($path, $name, 'image/png', null, true);
}

/**
 * Membuat varian yang siap dibeli (seller aktif, produk aktif, stok, gambar).
 */
function publishedVariant(int $stock = 10, int $price = 75_000, ?Seller $seller = null, ?string $sku = null): ProductVariant
{
    if (! $seller instanceof Seller) {
        [, $seller] = makeSeller();
    }

    $product = Product::factory()->for($seller)->active()->create([
        'min_price' => $price,
        'max_price' => $price,
    ]);

    $variant = ProductVariant::factory()->for($product)->create([
        'price' => $price,
        'sku' => $sku ?? 'SKU-'.Str::upper(Str::random(8)),
    ]);

    Inventory::factory()->create([
        'variant_id' => $variant->id,
        'stock' => $stock,
        'reserved_stock' => 0,
    ]);

    ProductImage::factory()->for($product)->create();

    return $variant->refresh();
}

/**
 * Membuat buyer dengan satu alamat pengiriman.
 *
 * @return array{0: User, 1: UserAddress}
 */
function buyerWithAddress(): array
{
    $buyer = User::factory()->create();
    $address = UserAddress::factory()->for($buyer)->default()->create();

    return [$buyer, $address];
}

/**
 * Menjalankan checkout lewat HTTP test.
 *
 * @param  array<int, array<string, mixed>>  $items
 * @param  array<int, array<string, mixed>>  $selections
 */
function doCheckout(
    User $buyer,
    UserAddress $address,
    array $items,
    string $key = 'idem-key-1',
    array $selections = [],
    ?string $voucherCode = null,
): TestResponse {
    return test()->postJson('/api/v1/checkout', [
        'items' => $items,
        'address_id' => $address->getKey(),
        'shipping_selections' => $selections,
        'voucher_code' => $voucherCode,
    ], array_merge(authHeadersFor($buyer), ['Idempotency-Key' => $key]));
}

/**
 * Membuat order (checkout) dan mengembalikan model order-nya.
 *
 * @param  array<int, array<string, mixed>>|null  $items
 */
function checkoutOrderFrom(User $buyer, UserAddress $address, ?array $items = null, string $key = 'checkout-order'): Order
{
    $items ??= [['variant_id' => publishedVariant()->id, 'quantity' => 1]];

    doCheckout($buyer, $address, $items, $key)->assertStatus(201);

    return $buyer->orders()->latest('id')->firstOrFail();
}

/**
 * Payload webhook palsu dengan signature valid.
 *
 * @return array<string, mixed>
 */
function fakeWebhookPayload(Order $order, string $transactionStatus = 'settlement', ?string $transactionId = null, ?int $amount = null): array
{
    return FakePaymentGateway::webhookPayload(
        $order->order_number,
        $amount ?? (int) $order->grand_total,
        $transactionStatus,
        (string) config('zentro.payment.fake_secret'),
        $transactionId,
    );
}

/**
 * Mengirim webhook pembayaran.
 *
 * @param  array<string, mixed>  $payload
 */
function postPaymentWebhook(array $payload, string $provider = 'fake', ?string $signature = null): TestResponse
{
    $headers = ['X-Webhook-Signature' => $signature ?? (string) ($payload['signature_key'] ?? '')];

    return test()->postJson('/api/v1/webhooks/payments/'.$provider, $payload, $headers);
}

/**
 * Checkout lalu bayar (webhook settlement) sehingga order menjadi `paid`.
 *
 * @param  array<int, array<string, mixed>>  $items
 */
function paidOrderFrom(User $buyer, UserAddress $address, array $items, string $key = 'paid-order'): Order
{
    doCheckout($buyer, $address, $items, $key)->assertStatus(201);

    $order = $buyer->orders()->latest('id')->firstOrFail();

    postPaymentWebhook(fakeWebhookPayload($order, 'settlement', 'tx-'.$key))->assertOk();

    return $order->refresh();
}

/**
 * Payload webhook tracking dengan signature valid.
 *
 * @param  array<int, array<string, mixed>>  $events
 * @return array<string, mixed>
 */
function fakeShippingWebhookPayload(Shipment $shipment, string $status = 'delivered', ?string $eventId = null, array $events = []): array
{
    return ManualShippingProvider::webhookPayload(
        $shipment->getKey(),
        $status,
        (string) config('zentro.shipping.webhook_secret'),
        $eventId,
        $shipment->tracking_number,
        $events,
    );
}

/**
 * Mengirim webhook tracking pengiriman.
 *
 * @param  array<string, mixed>  $payload
 */
function postShippingWebhook(array $payload, string $provider = 'fake', ?string $signature = null): TestResponse
{
    $headers = ['X-Webhook-Signature' => $signature ?? (string) ($payload['signature_key'] ?? '')];

    return test()->postJson('/api/v1/webhooks/shipping/'.$provider, $payload, $headers);
}

/**
 * Membuat voucher untuk pengujian.
 *
 * @param  array<string, mixed>  $overrides
 */
function makeVoucher(array $overrides = []): Voucher
{
    return Voucher::query()->create(array_merge([
        'code' => 'SAVE10',
        'name' => 'Diskon 10%',
        'type' => 'percent',
        'value' => 1000,
        'min_purchase' => 0,
        'max_discount' => null,
        'scope' => 'platform',
        'seller_id' => null,
        'funded_by' => 'platform',
        'usage_limit' => null,
        'usage_limit_per_user' => null,
        'used_count' => 0,
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addMonth(),
        'is_active' => true,
    ], $overrides));
}

/**
 * Membuat user + toko (default aktif).
 *
 * @return array{0: User, 1: Seller}
 */
function makeSeller(bool $active = true): array
{
    $user = User::factory()->create();
    $factory = Seller::factory()->for($user);
    $seller = $active ? $factory->active()->create() : $factory->create();

    return [$user, $seller];
}

/**
 * Membuat user admin (semua permission kecuali manage_users).
 */
function makeAdminUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

/**
 * Skenario finance: order paid → dikonfirmasi → dikirim → delivered → completed.
 *
 * @return array{0: User, 1: User, 2: Seller, 3: Order, 4: SellerOrder, 5: Shipment}
 */
function financeSellerScenario(string $key, int $price = 100_000, int $quantity = 1): array
{
    [$buyer, $address] = buyerWithAddress();
    [$sellerUser, $seller] = makeSeller();
    $variant = publishedVariant(stock: 10, price: $price, seller: $seller);

    $order = paidOrderFrom($buyer, $address, [['variant_id' => $variant->id, 'quantity' => $quantity]], $key);
    $sellerOrder = $order->sellerOrders()->firstOrFail();

    app(ConfirmSellerOrderAction::class)->execute($sellerOrder, $sellerUser->getKey());

    $shipment = $sellerOrder->shipment()->firstOrFail();
    app(DispatchShipmentAction::class)->execute($shipment, ['tracking_number' => 'FIN-'.strtoupper($key)], $sellerUser->getKey());

    postShippingWebhook(fakeShippingWebhookPayload($shipment->refresh(), 'delivered', 'evt-'.$key))->assertOk();

    app(ConfirmReceivedAction::class)->execute($order->refresh(), $buyer->getKey());

    return [$buyer, $sellerUser, $seller, $order->refresh(), $sellerOrder->refresh(), $shipment->refresh()];
}

/**
 * Melepas settlement komisi (set completed_at mundur lalu jalankan action).
 */
function settleCommission(Commission $commission, int $daysAgo = 8): void
{
    $commission->sellerOrder()->firstOrFail()
        ->forceFill(['completed_at' => now()->subDays($daysAgo)])
        ->save();

    app(ReleaseSettlementAction::class)->execute($commission->refresh());
}

/**
 * Membuat rekening bank seller (default).
 */
function sellerBankAccount(Seller $seller, array $overrides = []): SellerBankAccount
{
    return SellerBankAccount::query()->create(array_merge([
        'seller_id' => $seller->getKey(),
        'bank_name' => 'BCA',
        'account_number' => '1234567890',
        'account_name' => $seller->store_name,
        'is_default' => true,
    ], $overrides));
}

/**
 * Payload produk (dengan satu varian). `category_id` dibuat otomatis bila tidak diberikan.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function productPayload(array $overrides = []): array
{
    $categoryId = $overrides['category_id']
        ?? Category::factory()->create()->id;

    return array_merge([
        'name' => 'Kaos Polos Premium '.Str::upper(Str::random(4)),
        'category_id' => $categoryId,
        'description' => 'Kaos katun premium.',
        'weight_gram' => 200,
        'variants' => [[
            'sku' => 'SKU-'.Str::upper(Str::random(8)),
            'variant_name' => 'Default',
            'price' => 75000,
            'weight_gram' => 200,
            'stock' => 10,
        ]],
    ], $overrides);
}
