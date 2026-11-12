# 17 — Phase 6 Implementation Report (Shipping)

**Date:** 2026-11-10
**Status:** ✅ Selesai & terverifikasi (189 test hijau, konkurensi webhook tracking terbukti)
**Scope:** Pengiriman per seller, resi & tracking, agregasi status parent order, konfirmasi terima, auto-complete, quote ongkir.

---

## 1. Objective

- Setiap `seller_order` diirim oleh seller-nya sendiri dengan resi/tracking terpisah.
- Dispatch mengubah sub-order → `shipped` dan parent order dihitung ulang otomatis.
- Tracking webhook mengubah shipment → `in_transit`/`delivered` dan sub-order → `delivered`.
- Buyer melihat tracking dan menandai diterima; auto-complete setelah masa hold.
- Seller dapat melihat & memproses pesanan yang harus dikirim.
- Webhook aman: signature, durable, idempotent, tanpa transisi ganda.

## 2. Keputusan (ditetapkan 2026-11-10)

| # | Keputusan | Pilihan |
|---|---|---|
| 1 | **B-10** provider shipping | **Manual** — seller input resi; tanpa agregator. `ShippingProviderInterface` siap untuk adapter baru |
| 2 | Pembuatan shipment | **Otomatis** saat `paid` (idempoten); endpoint create+dispatch tetap tersedia |
| 3 | `confirm-received` buyer | **Default semua** sub-order `delivered → completed`; opsional per `seller_order_id` |
| 4 | Auto-complete | **Aktif di Phase 6** (harian, `auto_complete_days` default 7) |
| 5 | Cancel setelah paid | **Ditunda ke Phase 7** (butuh aturan refund escrow) |

## 3. Architecture Decision

| Aspek | Keputusan |
|---|---|
| Modul | `app/Modules/Shipping` (baru) + `OrderStatusAggregator` di modul Order |
| Relasi | 1 `seller_order` = 1 shipment (MVP), **tanpa UNIQUE** di DB (partial shipment siap) |
| Shipment dibuat | `CreateShipmentAction::ensure()` dipanggil dari `MarkPaymentPaidAction` |
| Dispatch | Resi wajib; `confirmed → processing → shipped` dalam satu aksi (dua transisi legal, dua baris history) |
| Parent status | `OrderStatusAggregator` — tidak pernah diubah manual |
| Webhook | Durable (`shipping_webhook_events`) + idempotent (`provider_event_id` & `shipment_histories.provider_event_id`) |
| Tracking sync | `SyncShipmentTrackingJob` tiap 15 menit (manual = no-op) |
| Auto-complete | `AutoCompleteShipmentsJob` harian |
| Ongkir | Snapshot dari `seller_order.shipping_cost`; quote tetap `ShippingRateProvider` |
| Alamat | Snapshot `order_addresses` (Phase 4); `origin_city` dari seller origin |
| Model uang | Tidak berubah (Phase 7) |

## 4. Files Created / Modified

### Dibuat
```
app/Enums/{ShipmentStatus,ShippingWebhookEventStatus}.php
app/Contracts/Shipping/ShippingProviderInterface.php
app/Infrastructure/Shipping/{ManualShippingProvider,FakeShippingProvider}.php
app/Modules/Shipping/Models/{Shipment,ShipmentHistory,ShippingWebhookEvent}.php
app/Modules/Shipping/Support/{ShippingProviderManager,ShipmentStatusMapper}.php
app/Modules/Shipping/Support/Dto/{ShipmentCreationResult,ShipmentTrackingResult,ShipmentTrackingEvent,ShipmentWebhookData}.php
app/Modules/Shipping/Actions/{CreateShipmentAction,ConfirmSellerOrderAction,DispatchShipmentAction,UpdateShipmentStatusAction,ConfirmReceivedAction,ProcessShippingWebhookAction}.php
app/Modules/Shipping/Http/Controllers/{ShippingQuoteController,ShipmentTrackingController,ShippingWebhookController,DevShipmentSimulatorController,OrderDeliveryController}.php
app/Modules/Shipping/Http/Controllers/Seller/{SellerOrderController,SellerShipmentController}.php
app/Modules/Shipping/Http/Requests/{ShippingQuoteRequest,DispatchShipmentRequest,ConfirmSellerOrderRequest,ConfirmReceivedRequest}.php
app/Modules/Shipping/Http/Resources/ShipmentResource.php
app/Modules/Shipping/Policies/ShipmentPolicy.php
app/Modules/Shipping/Routes/api.php
app/Modules/Order/Support/OrderStatusAggregator.php
app/Modules/Order/Policies/SellerOrderPolicy.php
app/Jobs/{SyncShipmentTrackingJob,AutoCompleteShipmentsJob}.php
database/migrations/2026_11_10_1400{01..03}_*.php
tests/Feature/Shipping/* (6 file) · tests/Unit/ShipmentStatusTest.php
```

### Dimodifikasi
```
app/Modules/Order/Models/Order.php (+shipments HasManyThrough)
app/Modules/Order/Models/SellerOrder.php (+shipment HasOne)
app/Modules/Order/Http/Resources/SellerOrderResource.php (+shipment, timestamps)
app/Modules/Order/Policies/OrderPolicy.php (+confirmReceived)
app/Modules/Payment/Actions/MarkPaymentPaidAction.php (+auto-create shipment)
app/Modules/Seller/Http/Controllers/SellerDashboardController.php (statistik nyata)
app/Providers/AppServiceProvider.php (+binding shipping provider, +limiter shipping-action)
app/Support/Exceptions/ErrorCode.php (+6 kode)
config/zentro.php (+shipping driver/webhook secret)
routes/api.php, routes/console.php
phpunit.xml, .env.example, tests/Pest.php
```

## 5. Database Changes

3 tabel baru — total tabel dev: **44**.

| Tabel | Catatan |
|---|---|
| `shipments` | `tracking_number` UNIQUE, index `(seller_id,status)`, CHECK biaya/berat ≥ 0; `seller_order_id` tanpa UNIQUE |
| `shipment_histories` | append-only; `provider_event_id` UNIQUE (idempotensi timeline); index `(shipment_id, occurred_at)` |
| `shipping_webhook_events` | **deviasi terdokumentasi** (docs/03 H3); `provider_event_id` UNIQUE |

## 6. Implementation

| Method | Endpoint | Catatan |
|---|---|---|
| GET | `/api/v1/shipping/quotes` | Quote per seller (flat) |
| GET | `/api/v1/seller/orders` | Hanya milik seller; filter status; pagination |
| GET | `/api/v1/seller/orders/{id}` | Detail + shipment + alamat pembeli |
| POST | `/api/v1/seller/orders/{id}/confirm` | `pending → confirmed` |
| POST | `/api/v1/seller/orders/{id}/shipments` | Create + dispatch |
| GET | `/api/v1/seller/shipments` | Hanya milik seller |
| GET | `/api/v1/seller/shipments/{id}` | Detail + timeline |
| POST | `/api/v1/seller/shipments/{id}/dispatch` | Resi → `dispatched` + sub-order `shipped` |
| GET | `/api/v1/shipments/{id}/tracking` | Seller/buyer/admin |
| POST | `/api/v1/orders/{orderNumber}/confirm-received` | `delivered → completed` |
| POST | `/api/v1/webhooks/shipping/{provider}` | Tanpa bearer; signature + `throttle:webhook` |
| POST | `/api/v1/dev/shipments/{id}/track` | **Hanya `APP_ENV=local` + driver fake** |

**Alur dispatch:** lock shipment + seller_order → validasi status & resi unik → shipment `dispatched` + history → sub-order `confirmed→processing→shipped` (+2 history) → agregasi parent.

**Alur tracking:** signature → durable event → cari shipment (id/provider id/tracking) → `canTransitionTo` → update shipment + history (+`provider_event_id`) → bila `delivered`: sub-order `delivered` → agregasi parent. Duplikat = no-op.

**Agregasi:** semua `pending` → `paid`; ada `confirmed/processing` → `processing`; sebagian dikirim → `partially_shipped`; semua dikirim/diterima → `shipped`; semua `completed` → `completed`.

## 7. Security Considerations

| Kontrol | Status |
|---|---|
| Isolasi antar seller (list/detail/confirm/dispatch) | ✅ Policy + test 403 |
| Buyer hanya melihat tracking order miliknya | ✅ `ShipmentPolicy` + test |
| Signature webhook | ✅ HMAC-SHA256 + test 401 |
| Idempotensi webhook & timeline | ✅ unique `provider_event_id` + test |
| Resi unik | ✅ UNIQUE DB + 409 |
| Dispatch tanpa pembayaran/konfirmasi | ✅ guard status + test 409 |
| Transisi status terlarang | ✅ `canTransitionTo` + unit test |
| Provider tak dikenal | ✅ 404 |
| Rate limit aksi seller | ✅ 10/menit/user |

## 8. Testing Results

```
Backend : 189 passed (843 assertions) — Pest 4.7.8   [41 test baru Phase 6]
Pint    : PASS

Uji konkurensi NYATA (dua proses PHP terpisah, 2 event delivered berbeda):
  Worker A: processed   Worker B: processed
  Akhir: shipment=delivered, seller_order=delivered, order=shipped,
         delivered_history_rows=1 ✅ (satu transisi)
```

Cakupan test Phase 6:
- Shipment: auto-create per seller saat paid, tidak sebelum bayar, idempoten, snapshot biaya/berat/kota, isolasi seller, confirm, guard confirm/dispatch, dispatch + resi unik + idempoten, tracking buyer/403.
- Webhook: delivered (shipment+suborder+parent), ganda (idempotent), dua event berbeda, in_transit, failed, resolve via tracking number, signature invalid, provider tak dikenal, shipment tak dikenal.
- Agregator: paid → processing → partially_shipped → shipped → completed; history agregasi.
- Confirm-received: semua/per seller, sebelum delivered 409, non-owner 403, idempoten.
- Job: auto-complete (lewat/belum hold), sync tracking (berubah/no-op).
- Unit: transisi shipment, pemetaan status provider (termasuk alias), flag final.

## 9. How to Run

```powershell
cd C:\laragon\www\Zentro\backend
php artisan migrate
php artisan test
php artisan serve
php artisan schedule:work   # terminal terpisah (sync tracking & auto-complete)
```

Simulasi tracking (lokal, `SHIPPING_DRIVER=fake`):
```http
POST /api/v1/dev/shipments/1/track
{ "status": "delivered" }
```

Alur seller (contoh):
```http
POST /api/v1/seller/orders/{id}/confirm
POST /api/v1/seller/orders/{id}/shipments
{ "tracking_number": "JNE123", "provider": "jne", "service_code": "REG" }
```

Mengaktifkan agregator nanti: implementasikan `ShippingProviderInterface`, daftarkan pada binding `zentro.shipping.driver`, dan ganti quote provider — alur tidak berubah.

## 10. Known Limitations

1. Satu shipment per sub-order; partial shipment per item belum aktif.
2. Quote masih tarif tetap (agregator menunggu B-10 lanjutan & kredensial).
3. Refund/retur untuk `failed`/`returned` belum memindahkan dana (Phase 7).
4. Auto-complete belum memicu settlement/komisi (Phase 7).
5. Belum ada label PDF/pickup request.
6. Cancel setelah paid/partial belum diaktifkan.
7. Notifikasi perubahan status belum dikirim (B-16).
8. `revenue_this_month` dashboard memakai proxy sub-order `completed` (wallet = Phase 7).

## 11. Next Phase

**Phase 7 — Commission & Finance**: `commission_rules`, `commissions`, `seller_wallets`, `seller_payouts`, `wallet_transactions`, `payout_items`; `CommissionCalculator` (basis point, snapshot 5%, ongkir tidak termasuk), settlement hold → available, ledger immutable, payout idempotent, refund adjustment, rekonsiliasi harian.

**Prasyarat keputusan:** B-5 (fee gateway), B-9 (kebijakan refund), B-13 (dual control payout).
