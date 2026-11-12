# 16 — Phase 5 Implementation Report (Payment)

**Date:** 2026-11-10
**Status:** ✅ Selesai & terverifikasi (148 test hijau, konkurensi webhook ganda terbukti)
**Scope:** Payment setelah checkout, adapter gateway (Midtrans + fake), webhook idempotent, commit reservasi stok, retry pembayaran, expiry & rekonsiliasi.

---

## 1. Objective

- Setiap order `pending_payment` memiliki tepat satu payment (`amount = order.grand_total`).
- Pembayaran sukses mengubah order → `paid` dan meng-commit reservasi stok **tepat sekali**.
- Webhook aman: signature, durable, idempotent, amount check, selalu `200` bila signature valid.
- Order `expired`/`cancelled` tidak pernah bisa menjadi `paid`.
- Buyer dapat melihat status & mencoba ulang pembayaran.
- Tanpa kredensial Midtrans pun alur tetap lengkap & teruji (fake gateway).

## 2. Architecture Decision

| Aspek | Keputusan |
|---|---|
| Modul | `app/Modules/Payment` (baru) + `CommitReservationAction` di Inventory |
| Relasi | 1 order = 1 payment (`payments.order_id` UNIQUE) |
| Gateway | `PaymentGatewayInterface` + `FakePaymentGateway` (test/lokal) + `MidtransGateway` (Core API, aktif di sandbox) |
| Panggilan jaringan | **Di luar** transaksi DB (pola Phase 4) |
| Webhook | Durable dulu (`payment_webhook_events`), `provider_event_id` UNIQUE, diproses dengan lock |
| Idempotensi transisi | `SELECT ... FOR UPDATE` pada `payments` + cek `status != paid` |
| Commit stok | `CommitReservationAction`: hanya reservasi `active`, lock `inventories` urut `variant_id` |
| Status order | `pending_payment → paid` + `order_status_histories` (agregasi suborder menyusul Phase 6) |
| Midtrans | Core API `/v2/charge` per metode (QRIS, e-wallet, VA semua bank); signature `sha512(order_id+status_code+gross_amount+signature_key)` |
| Refund | Tabel + enum + model + guard transisi; **perpindahan dana di Phase 7** |

## 3. Files Created / Modified

### Dibuat
```
app/Enums/{PaymentStatus,PaymentAttemptStatus,PaymentWebhookEventStatus,RefundStatus}.php
app/Contracts/Payment/PaymentGatewayInterface.php
app/Infrastructure/Payment/{FakePaymentGateway,MidtransGateway}.php
app/Modules/Payment/Models/{Payment,PaymentAttempt,PaymentWebhookEvent,Refund}.php
app/Modules/Payment/Support/{PaymentGatewayManager,PaymentStatusMapper}.php
app/Modules/Payment/Support/Dto/{PaymentInitiation,PaymentStatusResult,PaymentWebhookData,RefundResult}.php
app/Modules/Payment/Actions/{InitiatePaymentAction,MarkPaymentPaidAction,ProcessPaymentWebhookAction}.php
app/Modules/Payment/Http/Controllers/{PaymentController,PaymentWebhookController,DevPaymentSimulatorController}.php
app/Modules/Payment/Http/Resources/PaymentResource.php
app/Modules/Payment/Routes/api.php
app/Modules/Inventory/Actions/CommitReservationAction.php
app/Jobs/{ExpirePaymentsJob,ReconcilePaymentsJob}.php
database/migrations/2026_11_10_1300{01..04}_*.php
tests/Feature/Payment/{PaymentCreationTest,PaymentWebhookTest,PaymentJobTest}.php
tests/Unit/{PaymentStatusMapperTest,RefundStatusTest}.php
```

### Dimodifikasi
```
app/Modules/Order/Models/Order.php (+payment HasOne)
app/Modules/Order/Policies/OrderPolicy.php (+retryPayment)
app/Modules/Order/Http/Resources/{OrderResource,OrderDetailResource}.php (+payment)
app/Modules/Order/Http/Controllers/{CheckoutController,OrderController}.php (initiate payment & eager load)
app/Modules/Order/Actions/CancelOrderAction.php (payment non-final → expired)
app/Providers/AppServiceProvider.php (+binding gateway, +rate limiter payment-retry/webhook)
app/Support/Exceptions/ErrorCode.php (+7 kode)
config/zentro.php (+seksi payment)
routes/api.php, routes/console.php
phpunit.xml, .env.example
tests/Pest.php (+helper payment)
```

## 4. Database Changes

4 tabel baru — total tabel dev: **41**.

| Tabel | Catatan |
|---|---|
| `payments` | `order_id` UNIQUE, `(provider, provider_reference)` UNIQUE, CHECK `amount > 0` |
| `payment_attempts` | append-only, index `(payment_id, created_at)` |
| `payment_webhook_events` | `provider_event_id` UNIQUE (kunci idempotensi), index `(status, received_at)` |
| `refunds` | struktur + guard; eksekusi dana Phase 7 |

## 5. Implementation

| Method | Endpoint | Catatan |
|---|---|---|
| GET | `/api/v1/orders/{orderNumber}/payment` | Pemilik/admin; `404 PAYMENT_NOT_FOUND` bila belum ada |
| POST | `/api/v1/orders/{orderNumber}/payment/retry` | Buat `payment_attempts` baru; `409` bila sudah paid/tidak bisa |
| POST | `/api/v1/webhooks/payments/{provider}` | Tanpa bearer; signature + `throttle:webhook` (300/menit/IP) |
| POST | `/api/v1/dev/payments/{orderNumber}/simulate` | **Hanya `APP_ENV=local` + driver fake** (simulator manual) |

**Alur checkout → payment:** checkout commit → `InitiatePaymentAction` (di luar transaksi) → `payments` (`requires_action`) + `payment_attempts` → response `data.payment` berisi `token`/`redirect_url`. Replay idempotency tidak memanggil provider lagi.

**Alur webhook:** signature → durable event → cek event `processed` (replay 200) → cari order/payment → amount check → lock payment → `paid` (order + commit reservasi) atau update status non-lunas.

**Commit stok:** `stock -= qty`, `reserved_stock -= qty`, reservasi → `committed`, movement `commit` (referensi order), `CatalogCache::bump()`.

## 6. Security Considerations

| Kontrol | Status |
|---|---|
| Signature webhook (SHA512 Midtrans / HMAC fake) | ✅ + test 401 |
| `provider_event_id` unik (idempotensi) | ✅ + test webhook ganda |
| Amount check sebelum commit | ✅ + test mismatch |
| Dua transisi `→ paid` | ✅ ditolak (lock + cek status) |
| Order `expired` → `paid` | ✅ ditolak |
| Akses payment | ✅ Policy (buyer/admin), 403 untuk orang lain |
| Secret tidak disimpan | ✅ `provider_payload` di-redact |
| Rate limit webhook | ✅ 300/menit/IP |
| Panggilan gateway dalam transaksi | ✅ tidak ada |
| Refund dari status non-paid | ✅ diabaikan |

## 7. Testing Results

```
Backend : 148 passed (572 assertions) — Pest 4.7.8   [32 test baru Phase 5]
Pint    : PASS

Uji konkurensi NYATA (dua proses PHP terpisah, order sama, dua event paid berbeda):
  Worker A: Webhook diterima. event=processed
  Worker B: Webhook diterima. event=processed
  Akhir: order=paid, payment=paid, stock=0, reserved=0,
         reservation=[committed], commit_movements=1 (sum -1) ✅ tepat sekali
```

Cakupan test Phase 5:
- Payment: dibuat saat checkout, amount = grand_total, replay tidak menggandakan payment/attempt, akses owner/403/401, 404 tanpa payment, retry + attempt baru, retry saat paid → 409, gateway gagal → order tetap valid lalu retry berhasil.
- Webhook: paid → order paid + stock commit, webhook ganda idempotent, dua event paid berbeda → commit 1×, amount mismatch, signature invalid, order tidak dikenal, provider tidak dikenal, order expired → ignored, failed/expire tidak menyentuh stok, refund dari paid.
- Job: expiry payment, paid tidak tersentuh, rekonsiliasi settlement tanpa webhook, rekonsiliasi expired, rekonsiliasi idempotent.
- Unit: pemetaan status provider, guard transisi refund.

## 8. How to Run

```powershell
cd C:\laragon\www\Zentro\backend
php artisan migrate
php artisan test
php artisan serve

# scheduler (terminal terpisah)
php artisan schedule:work
```

Simulasi pembayaran (lingkungan `local`, driver `fake`):
```http
POST /api/v1/dev/payments/ZTR-YYYYMMDD-000001/simulate
{ "status": "paid" }
```

Mengaktifkan Midtrans (Core API, tanpa Snap):
```
PAYMENT_GATEWAY=midtrans
MIDTRANS_SERVER_KEY=...
MIDTRANS_CLIENT_KEY=...
MIDTRANS_SIGNATURE_KEY=...
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_PAYMENTS=qris,gopay,bca_va,bni_va,bri_va,cimb_va,permata_va,mandiri_va
```
Tidak ada perubahan kode; webhook URL: `/api/v1/webhooks/payments/midtrans`.
Panduan lengkap (3 jenis key, metode, ngrok, checklist produksi): [docs/22](22-midtrans-integration.md).

> **Pembaruan 2026-11-11 — Midtrans sandbox aktif & terverifikasi.**
> Integrasi dipindah dari Snap ke **Core API `/v2/charge`** agar pembeli memilih metode di dalam
> aplikasi (tanpa redirect): QRIS, GoPay/ShopeePay, dan VA **semua bank** (BCA, BNI, BRI, CIMB,
> Permata, Mandiri Bill). Pembeli memilih metode di halaman pembayaran (`POST .../payment/retry`
> dengan `method`); checkout tidak lagi membuat charge otomatis.
> Temuan penting saat uji nyata: (1) notifikasi ditandatangani **Signature Secret Key** yang berbeda
> dari server key → `MIDTRANS_SIGNATURE_KEY`; (2) PHP butuh CA bundle (`curl.cainfo`); (3) saat
> pembeli ganti metode, transaksi lama di-`expire` → notifikasi `expire`-nya diabaikan lewat
> `swept_transaction_id`.

## 9. Known Limitations

1. **Kartu kredit belum didukung** (butuh 3DS/Snap); metode lain sudah teruji sandbox per 2026-11-11.
2. Commit stok penuh per order (belum parsial per suborder) — Phase 6.
3. `seller_orders` tetap `pending` setelah paid; konfirmasi seller & agregasi status parent → Phase 6.
4. Refund belum memindahkan dana / membalik komisi → Phase 7.
5. `sold_count` belum dinaikkan.
6. Notifikasi buyer/seller belum dikirim.
7. Fee gateway belum dibukukan (B-5 belum diputuskan).
8. Simulator dev hanya aktif pada `APP_ENV=local`.

## 10. Next Phase

**Phase 6 — Shipping**: `shipments` & `shipment_histories`, `GET /shipping/quotes` + adapter agregator, seller dispatch/tracking, webhook shipping, status suborder (`processing → shipped → delivered`).
