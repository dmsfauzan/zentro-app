# 15 — Phase 4 Implementation Report (Cart & Checkout)

**Date:** 2026-11-09
**Status:** ✅ Selesai & terverifikasi (116 test hijau, konkurensi nyata terbukti)
**Scope:** Keranjang, preview checkout, checkout idempotent, split per seller, snapshot, reservasi stok atomik, pembatalan & kedaluwarsa order.

---

## 1. Objective

- Pembeli mengelola keranjang lintas seller.
- Checkout menghitung ulang harga/ongkir dari server (bukan dari klien).
- Satu checkout menghasilkan **1 parent order + N sub-order** per seller.
- Stok direservasi **atomik** sehingga tidak terjadi overselling.
- Checkout aman terhadap retry (idempotency).
- Order kedaluwarsa otomatis dibatalkan dan stok dilepas.

## 2. Architecture Decision

| Aspek | Keputusan |
|---|---|
| Modul | `app/Modules/Cart`, `app/Modules/Order`, `app/Modules/Inventory` |
| Harga | Selalu dibaca ulang dari DB; `price`/`line_total` dari klien diabaikan |
| Reservasi | `SELECT ... FOR UPDATE` pada baris `inventories` (diurutkan `variant_id` untuk mengurangi deadlock) + CHECK `reserved <= stock` |
| Split | Grup per `seller_id` → `seller_orders` + `order_items` dengan snapshot |
| Ongkir | `ShippingRateProvider` (kontrak) + `FlatRateShippingProvider` (Phase 4); Phase 6 mengganti implementasi tanpa ubah checkout |
| Idempotency | Tabel `idempotency_keys` (hash payload); key disimpan **di dalam** transaksi bisnis |
| Snapshot | Nama produk, SKU, varian, harga, kuantitas, berat, alamat pengiriman |
| Order number | `ZTR-YYYYMMDD-NNNNNN` + retry bila bentrok |
| Voucher | Ditunda; `voucher_code` → `422 VOUCHER_NOT_AVAILABLE` (jujur, bukan diabaikan diam-diam) |
| Scheduler | `ExpireOrdersJob` + `ReleaseExpiredReservationsJob` tiap menit |

## 3. Files Created / Modified

### Dibuat
```
app/Enums/{OrderStatus,SellerOrderStatus,InventoryReservationStatus}.php
app/Contracts/Shipping/ShippingRateProvider.php
app/Infrastructure/Shipping/FlatRateShippingProvider.php
app/Support/Idempotency/IdempotencyService.php
app/Modules/Cart/{Models/Cart,Models/CartItem,Actions/AddCartItemAction,Actions/UpdateCartItemAction,
  Http/Controllers/CartController,Http/Requests/{AddCartItemRequest,UpdateCartItemRequest},
  Http/Resources/CartItemResource,Policies/CartItemPolicy,Routes/api.php}
app/Modules/Order/{Models/Order,Models/SellerOrder,Models/OrderItem,Models/OrderAddress,
  Models/OrderStatusHistory,Models/SellerOrderStatusHistory,
  Actions/PlaceCheckoutAction,Actions/CancelOrderAction,
  Support/CheckoutCalculator,
  Http/Controllers/CheckoutController,Http/Controllers/OrderController,
  Http/Requests/{CheckoutRequest,CheckoutPreviewRequest,CancelOrderRequest},
  Http/Resources/{OrderResource,OrderDetailResource,SellerOrderResource,OrderItemResource,OrderAddressResource},
  Policies/OrderPolicy,Routes/api.php}
app/Modules/Inventory/{Models/InventoryReservation,Actions/ReleaseReservationAction}
app/Modules/Catalog/Support/VariantAvailability.php
app/Modules/Platform/Models/IdempotencyKey.php
app/Jobs/{ExpireOrdersJob,ReleaseExpiredReservationsJob}.php
database/migrations/2026_11_09_1300{01..09}_*.php
tests/Feature/Cart/CartTest.php
tests/Feature/Checkout/CheckoutTest.php
tests/Feature/Order/OrderTest.php
```

### Dimodifikasi
```
app/Support/Exceptions/ErrorCode.php (+8 kode)
app/Modules/Identity/Models/User.php (+cart, +orders)
app/Modules/Seller/Models/Seller.php (+sellerOrders)
app/Providers/AppServiceProvider.php (+binding ShippingRateProvider, +rate limiter checkout)
routes/api.php (+Cart & Order routes) · routes/console.php (+schedule)
config/zentro.php (+shipping, +order, +idempotency)
tests/Pest.php (+helper publishedVariant, buyerWithAddress, doCheckout)
app/Modules/Order/Support/CheckoutCalculator.php (memakai VariantAvailability bersama)
```

## 4. Database Changes

10 tabel baru:

| Tabel | Catatan |
|---|---|
| `carts` | `user_id` UNIQUE (satu cart per user) |
| `cart_items` | UNIQUE `(cart_id, variant_id)`, CHECK qty > 0 |
| `orders` | `order_number` UNIQUE, index `(buyer_id,created_at)`, `(status,expires_at)`, `(status,created_at)` |
| `seller_orders` | UNIQUE `(order_id, seller_id)`, index `(seller_id,status,created_at)`, kolom komisi (snapshot, diisi Phase 7) |
| `order_items` | snapshot lengkap; CHECK qty/price/line_total |
| `order_addresses` | snapshot alamat (per order; per seller menyusul Phase 6) |
| `order_status_histories` | append-only + index `(order_id,created_at)` |
| `seller_order_status_histories` | append-only + index `(seller_order_id,created_at)` |
| `inventory_reservations` | index `(order_id,status)`, `(status,expires_at)`; CHECK qty > 0 |
| `idempotency_keys` | `key` UNIQUE, `request_hash`, `response_snapshot`, `expires_at` |

Total tabel dev: **37**. Kolom `orders.voucher_id` masih tanpa FK (menunggu tabel `vouchers`).

## 5. Implementation

| Method | Endpoint | Catatan |
|---|---|---|
| GET | `/api/v1/cart` | item + harga terbaru + ringkasan |
| POST | `/api/v1/cart/items` | tambah/gabung; validasi stok |
| PATCH | `/api/v1/cart/items/{item}` | ubah qty |
| DELETE | `/api/v1/cart/items/{item}` | hapus |
| POST | `/api/v1/checkout/preview` | grup per seller, ongkir, total |
| POST | `/api/v1/checkout` | **wajib `Idempotency-Key`**; 201 |
| GET | `/api/v1/orders` | daftar order buyer |
| GET | `/api/v1/orders/{orderNumber}` | detail (pemilik/admin) |
| POST | `/api/v1/orders/{orderNumber}/cancel` | hanya `pending_payment` |

**Alur checkout (dalam satu transaksi):** normalisasi item → kunci baris inventory → validasi (produk/varian/seller aktif + stok) → hitung grup & ongkir → buat order + sub-order + item + snapshot alamat + riwayat status → naikkan `reserved_stock` + buat reservasi + movement → hapus item cart terkait → simpan idempotency → commit.

## 6. Security Considerations

| Kontrol | Status |
|---|---|
| Harga/total dari klien diabaikan | ✅ + test |
| Kepemilikan cart item | ✅ Policy + test 403 |
| Kepemilikan alamat saat checkout | ✅ 404 bila milik orang lain |
| Kepemilikan order | ✅ Policy (buyer/admin) |
| Idempotency (retry aman) | ✅ + test replay & konflik |
| Overselling | ✅ row lock + CHECK DB + uji 2 proses |
| Race `reserved_stock` | ✅ `lockForUpdate` |
| Panggilan eksternal di dalam transaksi | ✅ tidak ada |
| Rate limit checkout | ✅ 10/menit per user |
| Voucher tidak diabaikan diam-diam | ✅ 422 eksplisit |

## 7. Testing Results

```
Backend : 116 passed (419 assertions) — Pest 4.7.8   [29 test baru Phase 4]
Pint    : OK

Smoke HTTP: cart → preview → checkout → idempotency replay → orders → detail → cancel
            order ZTR-20261109-000001 (total 165000, 1 sub-order, snapshot OK),
            cancel → reserved_stock kembali 0 ✅

Uji konkurensi NYATA (dua proses PHP terpisah, stok = 1):
  Worker A: ERR INSUFFICIENT_STOCK
  Worker B: OK ZTR-20261109-000002
  Akhir: stock=1, reserved=1, available=0, reservasi active=1 ✅ (tanpa overselling)
```

Cakupan test Phase 4:
- Cart: auth, tambah + harga segar + ringkasan, akumulasi qty, batas stok, produk tidak tayang, update/delete, isolasi antar user.
- Checkout: wajib idempotency key, cart kosong, preview tanpa membuat order, split 2 seller (1 order + 2 sub-order), snapshot, reservasi + movement, cart dibersihkan, replay key sama, konflik payload, stok kurang tanpa order, **stok terakhir tidak oversold**, voucher ditolak, alamat orang lain 404, harga klien diabaikan, auth.
- Order: daftar hanya milik buyer, detail + 403 untuk orang lain, cancel melepas reservasi, cancel non-pending 409, job expiry, job release reservasi.

**Catatan proses:** satu bug penulisan (`$this->lastAddress`) tertangkap saat implementasi dan diperbaiki sebelum test dijalankan.

## 8. How to Run

```powershell
cd C:\laragon\www\onlineshop\backend
php artisan migrate
php artisan serve
php artisan test

# scheduler (terminal terpisah) untuk expiry otomatis
php artisan schedule:work
```

Contoh checkout:
```http
POST /api/v1/checkout
Idempotency-Key: 7f3c...
{
  "items": [{ "variant_id": 1, "quantity": 2 }],
  "address_id": 1,
  "shipping_selections": [{ "seller_id": 1, "service_code": "flat" }]
}
```

## 9. Known Limitations

1. **Ongkir masih tarif tetap** per seller (Phase 6 mengganti dengan agregator).
2. **Voucher belum aktif** (tabel & validasi menyusul; `voucher_code` ditolak eksplisit).
3. **Payment belum dibuat** — `payment: null` pada response; Phase 5 menambahkan pembuatan payment + webhook.
4. **Alamat snapshot hanya satu per order** (bukan per seller); partial shipment menyesuaikan di Phase 6.
5. **Belum ada endpoint seller untuk melihat order** (`/seller/orders`) — Phase 6.
6. **Order number berbasis hitungan harian + retry**; bukan sequence DB terdedikasi. Aman untuk skala awal.
7. **`sold_count` belum dinaikkan** (menunggu pembayaran/penyelesaian di Phase 5+).
8. **Reservasi belum di-commit** — stok fisik baru berkurang saat pembayaran sukses (Phase 5).
9. **Tidak ada partial cancellation** per sub-order pada Phase 4.

## 10. Next Phase

**Phase 5 — Payment**: `payments`, `payment_attempts`, `payment_webhook_events`, `refunds`; interface `PaymentGatewayInterface` + adapter Midtrans; pembuatan payment setelah checkout commit; webhook idempotent (signature + amount check) yang meng-commit reservasi dan menandai order `paid`; job rekonsiliasi.

**Prasyarat keputusan:** kredensial Midtrans (server/client key) untuk uji sandbox. Tanpa itu, Phase 5 akan memakai **fake gateway** agar alur & test tetap lengkap, lalu adapter Midtrans disiapkan untuk diaktifkan saat kredensial tersedia.
